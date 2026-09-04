<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Tests\Data\Collections;

use Divergence\Data\Collections\Collection;
use Divergence\Data\Collections\Factory\Factory;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\TestCase;

class CollectionMathTest extends TestCase
{
    public function testEmptyCollectionResults(): void
    {
        $collection = $this->collection([]);
        $selector = static fn (MathSample $sample): int|float => $sample->Value;

        $this->assertSame(0, $collection->sum($selector));
        $this->assertNull($collection->median($selector));
        $this->assertNull($collection->percentile($selector, 50));
        $this->assertNull($collection->quantile($selector, 0.5));
        $this->assertNull($collection->variance($selector));
        $this->assertNull($collection->stddev($selector));
        $this->assertSame([], $collection->histogram($selector));
        $this->assertSame([], $collection->mode($selector));
        $this->assertNull($collection->covariance($selector, $selector));
        $this->assertNull($collection->correlation($selector, $selector));
        $this->assertSame([], $collection->topK($selector, 3));
        $this->assertSame([], $collection->bottomK($selector, 3));
        $this->assertSame([], $collection->frequency($selector));
        $this->assertSame([], $collection->countBy($selector));
        $this->assertSame([], $collection->movingAverage($selector, 3));
        $this->assertSame([], $collection->rolling(3, static fn (array $records): array => $records));
        $this->assertSame([], $collection->zScore($selector));
        $this->assertSame([], $collection->outliers($selector));
    }

    public function testSingletonAndConstantDistributionResults(): void
    {
        $collection = $this->collection([5, 5, 5]);
        $selector = static fn (MathSample $sample): int|float => $sample->Value;

        $this->assertSame(5, $collection->median($selector));
        $this->assertSame(5, $collection->quantile($selector, 0));
        $this->assertSame(5, $collection->quantile($selector, 1));
        $this->assertSame(0.0, $collection->variance($selector));
        $this->assertSame(0.0, $collection->stddev($selector));
        $this->assertSame(
            [['min' => 5.0, 'max' => 5.0, 'count' => 3]],
            $collection->histogram($selector, 4)
        );
        $this->assertSame([5], $collection->mode($selector));
        $this->assertSame([0.0, 0.0, 0.0], $collection->zScore($selector));
        $this->assertSame([], $collection->outliers($selector));
        $this->assertNull($collection->correlation($selector, $selector));
    }

    public function testQuantileBoundariesAndNearestRanks(): void
    {
        $collection = $this->collection([40, 10, 30, 20]);
        $selector = static fn (MathSample $sample): int|float => $sample->Value;

        $this->assertSame(10, $collection->quantile($selector, 0));
        $this->assertSame(10, $collection->quantile($selector, 0.25));
        $this->assertSame(20, $collection->quantile($selector, 0.5));
        $this->assertSame(30, $collection->quantile($selector, 0.75));
        $this->assertSame(40, $collection->quantile($selector, 1));
        $this->assertSame(25, $collection->median($selector));
        $this->assertSame(10, $collection->percentile($selector, 0));
        $this->assertSame(20, $collection->percentile($selector, 50));
        $this->assertSame(40, $collection->percentile($selector, 100));
    }

    public function testQuantileRejectsInvalidRangesAndNonFiniteValues(): void
    {
        $collection = $this->collection([1, 2, 3]);
        $selector = static fn (MathSample $sample): int|float => $sample->Value;
        $operations = [
            static fn () => $collection->quantile($selector, -0.01),
            static fn () => $collection->quantile($selector, 1.01),
            static fn () => $collection->quantile($selector, NAN),
            static fn () => $collection->quantile($selector, INF),
            static fn () => $collection->percentile($selector, -1),
            static fn () => $collection->percentile($selector, 101),
            static fn () => $collection->percentile($selector, NAN),
            static fn () => $collection->percentile($selector, INF),
        ];

        $this->assertInvalidOperations($operations);
    }

    public function testNearestRankCorrectsFloatingPointBoundaryNoise(): void
    {
        $collection = $this->collection(range(1, 25));
        $selector = static fn (MathSample $sample): int|float => $sample->Value;

        $this->assertSame(7, $collection->quantile($selector, 0.28));
        $this->assertSame(7, $collection->percentile($selector, 28));

        $fourValues = $this->collection([1, 2, 3, 4]);
        $this->assertSame(2, $fourValues->quantile($selector, 0.250000000000001));
    }

    public function testSumVarianceAndStandardDeviationWithSignedDecimalValues(): void
    {
        $collection = $this->collection([-1.5, 0.0, 1.5]);
        $selector = static fn (MathSample $sample): int|float => $sample->Value;

        $this->assertSame(0.0, $collection->sum($selector));
        $this->assertSame(1.5, $collection->variance($selector));
        $this->assertEqualsWithDelta(sqrt(1.5), $collection->stddev($selector), 0.000000000001);
    }

    public function testMedianAndVariancePreserveFiniteNumericExtremes(): void
    {
        $maximumFloats = $this->collection([PHP_FLOAT_MAX, PHP_FLOAT_MAX]);
        $minimumFloats = $this->collection([5e-324, 5e-324]);
        $distinctMinimumFloats = $this->collection([5e-324, 1e-323]);
        $adjacentIntegers = $this->collection([PHP_INT_MAX - 1, PHP_INT_MAX]);
        $selector = static fn (MathSample $sample): int|float => $sample->Value;

        $this->assertSame(PHP_FLOAT_MAX, $maximumFloats->median($selector));
        $this->assertSame(5e-324, $minimumFloats->median($selector));
        $this->assertSame(1e-323, $distinctMinimumFloats->median($selector));
        $this->assertSame(0.0, $maximumFloats->variance($selector));
        $this->assertSame([0.0, 0.0], $maximumFloats->zScore($selector));
        $this->assertSame(0.25, $adjacentIntegers->variance($selector));
    }

    public function testSumUsesPhpNumericPromotionAndRejectsNonFiniteResults(): void
    {
        $integerOverflow = $this->collection([PHP_INT_MAX, 1]);
        $floatOverflow = $this->collection([PHP_FLOAT_MAX, PHP_FLOAT_MAX]);
        $selector = static fn (MathSample $sample): int|float => $sample->Value;

        $this->assertSame(array_sum([PHP_INT_MAX, 1]), $integerOverflow->sum($selector));
        $this->assertOverflowOperations([
            static fn () => $floatOverflow->sum($selector),
            static fn () => $floatOverflow->movingAverage($selector, 2),
        ]);
    }

    public function testNumericOperationsRejectNonNumericAndNonFiniteValues(): void
    {
        $collection = $this->collection(['1']);
        $selector = static fn (MathSample $sample) => $sample->Value;
        $operations = [
            static fn () => $collection->sum($selector),
            static fn () => $collection->median($selector),
            static fn () => $collection->percentile($selector, 50),
            static fn () => $collection->quantile($selector, 0.5),
            static fn () => $collection->variance($selector),
            static fn () => $collection->stddev($selector),
            static fn () => $collection->histogram($selector),
            static fn () => $collection->covariance($selector, $selector),
            static fn () => $collection->correlation($selector, $selector),
            static fn () => $collection->topK($selector, 1),
            static fn () => $collection->bottomK($selector, 1),
            static fn () => $collection->movingAverage($selector, 1),
            static fn () => $collection->zScore($selector),
            static fn () => $collection->outliers($selector),
        ];

        $this->assertInvalidOperations($operations);

        foreach ([NAN, INF, -INF] as $value) {
            $invalid = $this->collection([$value]);

            $this->assertInvalidOperations([
                static fn () => $invalid->sum($selector),
            ]);
        }
    }

    public function testHistogramUsesHalfOpenBucketsAndIncludesMaximum(): void
    {
        $collection = $this->collection([-10, -5, 0, 5, 10]);
        $histogram = $collection->histogram(
            static fn (MathSample $sample): int|float => $sample->Value,
            4
        );

        $this->assertSame([1, 1, 1, 2], array_column($histogram, 'count'));
        $this->assertSame(
            [
                ['min' => -10.0, 'max' => -5.0, 'count' => 1],
                ['min' => -5.0, 'max' => 0.0, 'count' => 1],
                ['min' => 0.0, 'max' => 5.0, 'count' => 1],
                ['min' => 5.0, 'max' => 10.0, 'count' => 2],
            ],
            $histogram
        );
        $this->assertCount(
            10,
            $collection->histogram(static fn (MathSample $sample): int|float => $sample->Value)
        );
    }

    public function testHistogramRejectsInvalidBucketCounts(): void
    {
        $collection = $this->collection([1]);
        $selector = static fn (MathSample $sample): int|float => $sample->Value;

        $this->assertInvalidOperations([
            static fn () => $collection->histogram($selector, 0),
            static fn () => $collection->histogram($selector, -1),
        ]);
    }

    public function testHistogramRejectsRangesThatOverflowFloatingPoint(): void
    {
        $collection = $this->collection([-PHP_FLOAT_MAX, PHP_FLOAT_MAX]);
        $selector = static fn (MathSample $sample): int|float => $sample->Value;

        $this->assertInvalidOperations([
            static fn () => $collection->histogram($selector, 2),
        ]);
    }

    public function testHistogramCorrectsFloatingPointBoundaryNoise(): void
    {
        $collection = $this->collection([0.0, 0.3, 1.0]);
        $histogram = $collection->histogram(
            static fn (MathSample $sample): int|float => $sample->Value,
            10
        );

        $this->assertSame([1, 0, 0, 1, 0, 0, 0, 0, 0, 1], array_column($histogram, 'count'));
        $this->assertSame(0.3, $histogram[3]['min']);
    }

    public function testHistogramRejectsUnrepresentableAndSubnormalRanges(): void
    {
        $largeIntegers = $this->collection([PHP_INT_MAX - 1, PHP_INT_MAX]);
        $narrowFloats = $this->collection([1e16, 1.0000000000000002e16]);
        $subnormalFloats = $this->collection([0.0, 5e-324]);
        $selector = static fn (MathSample $sample): int|float => $sample->Value;

        $this->assertInvalidOperations([
            static fn () => $largeIntegers->histogram($selector, 2),
            static fn () => $narrowFloats->histogram($selector, 10),
            static fn () => $subnormalFloats->histogram($selector, 10),
        ]);
    }

    public function testFrequencyKeepsPhpTypesDistinctAndGroupsSerializedValues(): void
    {
        $firstObject = new \stdClass();
        $secondObject = new \stdClass();
        $values = [null, false, 0, 0.0, '', true, 1, 1.0, '1', false, 0, $firstObject, $firstObject, $secondObject, [1], [1]];
        $collection = $this->collection($values);
        $frequencies = $collection->frequency(static fn (MathSample $sample) => $sample->Value);

        $this->assertCount(11, $frequencies);
        $this->assertSame(1, $frequencies[0]['count']);
        $this->assertSame(2, $frequencies[1]['count']);
        $this->assertSame(2, $frequencies[2]['count']);
        $this->assertSame(1, $frequencies[3]['count']);
        $this->assertSame(3, $frequencies[9]['count']);
        $this->assertSame(2, $frequencies[10]['count']);
        $this->assertSame($frequencies, $collection->countBy(static fn (MathSample $sample) => $sample->Value));
    }

    public function testModeReturnsEveryTieInFirstSeenOrder(): void
    {
        $collection = $this->collection(['B', 'A', 'C', 'A', 'B', 'D']);
        $unique = $this->collection(['X', 'Y', 'Z']);

        $this->assertSame(
            ['B', 'A'],
            $collection->mode(static fn (MathSample $sample): string => $sample->Value)
        );
        $this->assertSame(
            ['X', 'Y', 'Z'],
            $unique->mode(static fn (MathSample $sample): string => $sample->Value)
        );
    }

    public function testPopulationCovarianceAndPositiveNegativeCorrelation(): void
    {
        $positive = $this->collection([1, 2, 3], [2, 4, 6]);
        $negative = $this->collection([1, 2, 3], [-2, -4, -6]);
        $constant = $this->collection([1, 2, 3], [5, 5, 5]);
        $first = static fn (MathSample $sample): int|float => $sample->Value;
        $second = static fn (MathSample $sample): int|float => $sample->Other;

        $this->assertEqualsWithDelta(4 / 3, $positive->covariance($first, $second), 0.000000000001);
        $this->assertEqualsWithDelta(4 / 3, $positive->covariance($second, $first), 0.000000000001);
        $this->assertSame(1.0, $positive->correlation($first, $second));
        $this->assertSame(1.0, $positive->correlation($first, $first));
        $this->assertEqualsWithDelta(-4 / 3, $negative->covariance($first, $second), 0.000000000001);
        $this->assertSame(-1.0, $negative->correlation($first, $second));
        $this->assertNull($constant->correlation($first, $second));
        $this->assertSame(0.0, $constant->covariance($first, $second));
    }

    public function testCorrelationHandlesLargeAndSmallFiniteScales(): void
    {
        $large = $this->collection([0.0, 2e100]);
        $small = $this->collection([0.0, 2e-100]);
        $selector = static fn (MathSample $sample): int|float => $sample->Value;

        $this->assertSame(1.0, $large->correlation($selector, $selector));
        $this->assertSame(1.0, $small->correlation($selector, $selector));
    }

    public function testTopAndBottomKHandleLimitsAndStableTies(): void
    {
        $collection = $this->collection([3, 1, 3, 2]);
        $selector = static fn (MathSample $sample): int|float => $sample->Value;

        $this->assertSame(['S-001', 'S-003'], $this->sampleIds($collection->topK($selector, 2)));
        $this->assertSame(['S-002', 'S-004'], $this->sampleIds($collection->bottomK($selector, 2)));
        $this->assertSame([], $collection->topK($selector, 0));
        $this->assertSame(['S-001', 'S-003', 'S-004', 'S-002'], $this->sampleIds($collection->topK($selector, 10)));
    }

    public function testZeroRankCountDoesNotEvaluateSelector(): void
    {
        $collection = $this->collection([1]);
        $calls = 0;
        $selector = static function (MathSample $sample) use (&$calls): int {
            $calls++;
            return $sample->Value;
        };

        $this->assertSame([], $collection->topK($selector, 0));
        $this->assertSame([], $collection->bottomK($selector, 0));
        $this->assertSame(0, $calls);
    }

    public function testRankingUsesInputOrderInsteadOfArrayKeysForStableTies(): void
    {
        $first = new MathSample('FIRST', 1, 0);
        $second = new MathSample('SECOND', 1, 0);
        $collection = (new Factory())->create();
        $collection->Index = [0 => $first, -1 => $second];
        $selector = static fn (MathSample $sample): int|float => $sample->Value;

        $this->assertSame(['FIRST', 'SECOND'], $this->sampleIds($collection->topK($selector, 2)));
        $this->assertSame(['FIRST', 'SECOND'], $this->sampleIds($collection->bottomK($selector, 2)));
    }

    public function testTopAndBottomKRejectNegativeCounts(): void
    {
        $collection = $this->collection([1]);
        $selector = static fn (MathSample $sample): int|float => $sample->Value;

        $this->assertInvalidOperations([
            static fn () => $collection->topK($selector, -1),
            static fn () => $collection->bottomK($selector, -1),
        ]);
    }

    public function testMovingAverageAndRollingWindowBoundaries(): void
    {
        $collection = $this->collection([1, 2, 3, 4]);
        $selector = static fn (MathSample $sample): int|float => $sample->Value;

        $this->assertSame([1, 2, 3, 4], $collection->movingAverage($selector, 1));
        $this->assertSame([2.5], $collection->movingAverage($selector, 4));
        $this->assertSame([], $collection->movingAverage($selector, 5));
        $this->assertSame(
            [3, 5, 7],
            $collection->rolling(2, static fn (array $records): int => array_sum(array_map(
                static fn (MathSample $sample): int => $sample->Value,
                $records
            )))
        );
        $this->assertSame([10], $collection->rolling(4, static fn (array $records): int => array_sum(array_map(
            static fn (MathSample $sample): int => $sample->Value,
            $records
        ))));
        $this->assertSame([], $collection->rolling(5, static fn (array $records): array => $records));
    }

    public function testMovingAverageAndRollingRejectInvalidWindowsBeforeCallbacks(): void
    {
        $collection = $this->collection([1, 2, 3]);
        $selectorCalls = 0;
        $callbackCalls = 0;
        $selector = static function (MathSample $sample) use (&$selectorCalls): int {
            $selectorCalls++;
            return $sample->Value;
        };
        $callback = static function (array $records) use (&$callbackCalls): array {
            $callbackCalls++;
            return $records;
        };

        $this->assertInvalidOperations([
            static fn () => $collection->movingAverage($selector, 0),
            static fn () => $collection->movingAverage($selector, -1),
            static fn () => $collection->rolling(0, $callback),
            static fn () => $collection->rolling(-1, $callback),
        ]);
        $this->assertSame(0, $selectorCalls);
        $this->assertSame(0, $callbackCalls);
    }

    public function testZScoresAndInclusiveOutlierThresholds(): void
    {
        $collection = $this->collection([0, 0, 0, 10]);
        $selector = static fn (MathSample $sample): int|float => $sample->Value;
        $scores = $collection->zScore($selector);

        $this->assertEqualsWithDelta(-0.5773502691896257, $scores[0], 0.000000000001);
        $this->assertEqualsWithDelta(1.7320508075688772, $scores[3], 0.000000000001);
        $this->assertSame(['S-004'], $this->sampleIds($collection->outliers($selector, $scores[3])));
        $this->assertSame(['S-001', 'S-002', 'S-003', 'S-004'], $this->sampleIds($collection->outliers($selector, 0)));
    }

    public function testRankingAndOutlierSelectorsRunOncePerRecord(): void
    {
        $collection = $this->collection([1, 2, 100]);
        $rankCalls = 0;
        $outlierCalls = 0;
        $rankSelector = static function (MathSample $sample) use (&$rankCalls): int {
            $rankCalls++;
            return $sample->Value;
        };
        $outlierSelector = static function (MathSample $sample) use (&$outlierCalls): int {
            $outlierCalls++;
            return $sample->Value;
        };

        $collection->topK($rankSelector, 2);
        $collection->outliers($outlierSelector, 1);

        $this->assertSame(3, $rankCalls);
        $this->assertSame(3, $outlierCalls);
    }

    public function testOutliersRejectNegativeThresholds(): void
    {
        $collection = $this->collection([1]);
        $selector = static fn (MathSample $sample): int|float => $sample->Value;

        $this->assertInvalidOperations([
            static fn () => $collection->outliers($selector, -0.01),
            static fn () => $collection->outliers($selector, NAN),
            static fn () => $collection->outliers($selector, INF),
        ]);
    }

    public function testMathSelectorsSupportArrayRecords(): void
    {
        $collection = (new Factory())->create();
        $collection->Index = [
            ['Value' => 3],
            ['Value' => 1],
            ['Value' => 2],
        ];
        $selector = static fn (array $sample): int => $sample['Value'];

        $this->assertSame(6, $collection->sum($selector));
        $this->assertSame(2, $collection->median($selector));
        $this->assertSame(
            [['Value' => 3], ['Value' => 2]],
            $collection->topK($selector, 2)
        );
    }

    /**
     * @param list<mixed> $values
     * @param list<mixed> $otherValues
     */
    private function collection(array $values, array $otherValues = []): Collection
    {
        $records = [];

        foreach ($values as $index => $value) {
            $records[] = new MathSample(
                sprintf('S-%03d', $index + 1),
                $value,
                $otherValues[$index] ?? 0
            );
        }

        return (new Factory())->create($records);
    }

    /**
     * @param list<MathSample> $samples
     * @return list<string>
     */
    private function sampleIds(array $samples): array
    {
        return array_map(static fn (MathSample $sample): string => $sample->ID, $samples);
    }

    /**
     * @param list<callable(): mixed> $operations
     */
    private function assertInvalidOperations(array $operations): void
    {
        foreach ($operations as $operation) {
            try {
                $operation();
                $this->fail('Expected InvalidArgumentException was not thrown.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * @param list<callable(): mixed> $operations
     */
    private function assertOverflowOperations(array $operations): void
    {
        foreach ($operations as $operation) {
            try {
                $operation();
                $this->fail('Expected OverflowException was not thrown.');
            } catch (OverflowException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}

class MathSample
{
    public string $ID;
    public mixed $Value;
    public mixed $Other;

    public function __construct(string $id, $value, $other)
    {
        $this->ID = $id;
        $this->Value = $value;
        $this->Other = $other;
    }
}
