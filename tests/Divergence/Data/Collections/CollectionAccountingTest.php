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
use PHPUnit\Framework\TestCase;

class CollectionAccountingTest extends TestCase
{
    private Collection $ledger;
    private TaxJurisdiction $newYorkCity;

    protected function setUp(): void
    {
        $this->newYorkCity = new TaxJurisdiction('NYC', 8875);
        $chicago = new TaxJurisdiction('CHI', 10250);
        $portland = new TaxJurisdiction('PDX', 0);
        $receipts = [];
        $basketSubtotals = [
            499, 799, 1299, 1599, 1999, 2499, 2999, 3499, 3999, 4499, 4999, 5999,
            6999, 7999, 8999, 9999, 12500, 15000, 17500, 20000, 22500, 25000, 30000,
        ];

        for ($index = 0; $index < 200; $index++) {
            $jurisdictionPosition = $index % 20;

            if ($jurisdictionPosition < 8) {
                $jurisdiction = $this->newYorkCity;
            } elseif ($jurisdictionPosition < 15) {
                $jurisdiction = $chicago;
            } else {
                $jurisdiction = $portland;
            }

            $subtotalCents = $basketSubtotals[$index % count($basketSubtotals)] + (($index * 137) % 1800);

            if ($index > 0 && $index % 47 === 0) {
                $subtotalCents = -$subtotalCents;
            }

            if ($index === 73) {
                $subtotalCents = 75000;
            } elseif ($index === 149) {
                $subtotalCents = 125000;
            } elseif ($index === 199) {
                $subtotalCents = 250000;
            }

            $itemCount = 1 + (($index * 7) % 12);
            $receipts[] = new Receipt(
                sprintf('R-%03d', $index + 1),
                $subtotalCents,
                $jurisdiction,
                $itemCount,
                1704067200 + ($index * 900),
                40 + ($itemCount * 18) + (($index * 11) % 17)
            );
        }

        $this->ledger = (new Factory())->create($receipts, ['JurisdictionCode']);
    }

    public function testTwoNewYorkCityReceiptsSumExactly(): void
    {
        $receipts = (new Factory())->create([
            new Receipt('NYC-001', 10000, $this->newYorkCity, 3, 1704067200, 100),
            new Receipt('NYC-002', 5000, $this->newYorkCity, 1, 1704068100, 65),
        ]);

        $this->assertCount(2, $receipts);
        $this->assertSame(888, $receipts[0]->getTaxCents());
        $this->assertSame(444, $receipts[1]->getTaxCents());
        $this->assertSame(1332, $receipts->sum(static fn (Receipt $receipt): int => $receipt->getTaxCents()));
        $this->assertSame(16332, $receipts->sum(static fn (Receipt $receipt): int => $receipt->getTotalCents()));
    }

    public function testRefundTaxRoundsSymmetrically(): void
    {
        $refund = new Receipt('NYC-REFUND', -10000, $this->newYorkCity, 2, 1704069000, 82);

        $this->assertSame(-888, $refund->getTaxCents());
        $this->assertSame(-10888, $refund->getTotalCents());
    }

    public function testZeroRateAndZeroSubtotalProduceNoTax(): void
    {
        $portland = new TaxJurisdiction('PDX', 0);
        $sale = new Receipt('PDX-SALE', 10000, $portland, 2, 1704069000, 82);
        $refund = new Receipt('PDX-REFUND', -10000, $portland, 2, 1704069900, 82);
        $zero = new Receipt('NYC-ZERO', 0, $this->newYorkCity, 0, 1704070800, 40);

        $this->assertSame(0, $sale->getTaxCents());
        $this->assertSame(0, $refund->getTaxCents());
        $this->assertSame(0, $zero->getTaxCents());
    }

    public function testTaxRoundsBelowAtAndAboveHalfCentForSalesAndRefunds(): void
    {
        $chicago = new TaxJurisdiction('CHI', 10250);
        $cases = [
            [$this->newYorkCity, 569, 50],
            [$this->newYorkCity, 400, 36],
            [$this->newYorkCity, 62, 6],
            [$this->newYorkCity, -569, -50],
            [$this->newYorkCity, -400, -36],
            [$this->newYorkCity, -62, -6],
            [$chicago, 239, 24],
            [$chicago, 200, 21],
            [$chicago, 161, 17],
            [$chicago, -239, -24],
            [$chicago, -200, -21],
            [$chicago, -161, -17],
        ];

        foreach ($cases as [$jurisdiction, $subtotal, $expectedTax]) {
            $this->assertSame($expectedTax, $jurisdiction->calculateTax($subtotal));
        }
    }

    public function testTwoHundredReceiptLedgerSumsExactly(): void
    {
        $subtotal = $this->ledger->sum(static fn (Receipt $receipt): int => $receipt->getSubtotalCents());
        $tax = $this->ledger->sum(static fn (Receipt $receipt): int => $receipt->getTaxCents());
        $total = $this->ledger->sum(static fn (Receipt $receipt): int => $receipt->getTotalCents());

        $this->assertCount(200, $this->ledger);
        $this->assertSame(2348010, $subtotal);
        $this->assertSame(152281, $tax);
        $this->assertSame(2500291, $total);
        $this->assertSame($subtotal + $tax, $total);
    }

    public function testLedgerMedianAndNearestRankPercentiles(): void
    {
        $selector = static fn (Receipt $receipt): int => $receipt->getSubtotalCents();

        $this->assertSame(6492.5, $this->ledger->median($selector));
        $this->assertSame(6377, $this->ledger->quantile($selector, 0.5));
        $this->assertSame(30316, $this->ledger->percentile($selector, 95));
        $this->assertSame(75000, $this->ledger->percentile($selector, 99));
    }

    public function testLedgerPopulationVarianceAndStandardDeviation(): void
    {
        $selector = static fn (Receipt $receipt): int => $receipt->getSubtotalCents();

        $this->assertEqualsWithDelta(441097328.9175, $this->ledger->variance($selector), 0.0001);
        $this->assertEqualsWithDelta(21002.31722733232, $this->ledger->stddev($selector), 0.0000001);
    }

    public function testLedgerHistogramShowsDistributionAndLargeTransactions(): void
    {
        $selector = static fn (Receipt $receipt): int => $receipt->getSubtotalCents();
        $histogram = $this->ledger->histogram(
            $selector,
            5
        );
        $regularReceipts = (new Factory())->create($this->ledger->bottomK($selector, 197));
        $regularHistogram = $regularReceipts->histogram($selector, 5);

        $this->assertSame([197, 1, 1, 0, 1], array_column($histogram, 'count'));
        $this->assertSame(200, array_sum(array_column($histogram, 'count')));
        $this->assertSame([58, 79, 22, 22, 16], array_column($regularHistogram, 'count'));
        $this->assertSame(197, array_sum(array_column($regularHistogram, 'count')));
    }

    public function testLedgerFrequencyAndMode(): void
    {
        $selector = static fn (Receipt $receipt): string => $receipt->JurisdictionCode;
        $expected = [
            ['value' => 'NYC', 'count' => 80],
            ['value' => 'CHI', 'count' => 70],
            ['value' => 'PDX', 'count' => 50],
        ];

        $this->assertSame($expected, $this->ledger->frequency($selector));
        $this->assertSame($expected, $this->ledger->countBy($selector));
        $this->assertSame(['NYC'], $this->ledger->mode($selector));
    }

    public function testLedgerJurisdictionTotals(): void
    {
        $expected = [
            'NYC' => [80, 759095, 67366, 826461],
            'CHI' => [70, 828461, 84915, 913376],
            'PDX' => [50, 760454, 0, 760454],
        ];

        foreach ($expected as $code => [$count, $subtotal, $tax, $total]) {
            $receipts = $this->ledger->getAllByField('JurisdictionCode', $code);

            $this->assertCount($count, $receipts);
            $this->assertSame($subtotal, $receipts->sum(
                static fn (Receipt $receipt): int => $receipt->getSubtotalCents()
            ));
            $this->assertSame($tax, $receipts->sum(
                static fn (Receipt $receipt): int => $receipt->getTaxCents()
            ));
            $this->assertSame($total, $receipts->sum(
                static fn (Receipt $receipt): int => $receipt->getTotalCents()
            ));
        }
    }

    public function testLedgerCovarianceAndCorrelation(): void
    {
        $subtotal = static fn (Receipt $receipt): int => $receipt->getSubtotalCents();
        $total = static fn (Receipt $receipt): int => $receipt->getTotalCents();
        $itemCount = static fn (Receipt $receipt): int => $receipt->ItemCount;
        $processingTime = static fn (Receipt $receipt): int => $receipt->ProcessingMilliseconds;

        $this->assertEqualsWithDelta(
            454409196.98225,
            $this->ledger->covariance($subtotal, $total),
            0.0001
        );
        $this->assertEqualsWithDelta(
            0.9987046282158667,
            $this->ledger->correlation($subtotal, $total),
            0.0000000000001
        );
        $this->assertEqualsWithDelta(
            215.6434,
            $this->ledger->covariance($itemCount, $processingTime),
            0.0000000001
        );
        $this->assertEqualsWithDelta(
            0.9969041494789462,
            $this->ledger->correlation($itemCount, $processingTime),
            0.0000000000001
        );
    }

    public function testLedgerTopAndBottomTransactions(): void
    {
        $selector = static fn (Receipt $receipt): int => $receipt->getSubtotalCents();
        $top = $this->ledger->topK($selector, 3);
        $bottom = $this->ledger->bottomK($selector, 3);

        $this->assertSame(
            ['R-200', 'R-150', 'R-074'],
            array_map(static fn (Receipt $receipt): string => $receipt->ReceiptNumber, $top)
        );
        $this->assertSame(
            ['R-142', 'R-189', 'R-048'],
            array_map(static fn (Receipt $receipt): string => $receipt->ReceiptNumber, $bottom)
        );
    }

    public function testLedgerRollingCalculationsPreserveReceiptOrder(): void
    {
        $movingAverage = $this->ledger->movingAverage(
            static fn (Receipt $receipt): int => $receipt->getSubtotalCents(),
            3
        );
        $rollingTotal = $this->ledger->rolling(
            3,
            static fn (array $receipts): int => array_sum(array_map(
                static fn (Receipt $receipt): int => $receipt->getTotalCents(),
                $receipts
            ))
        );

        $this->assertCount(198, $movingAverage);
        $this->assertEqualsWithDelta(1002.6666666666666, $movingAverage[0], 0.000000000001);
        $this->assertCount(198, $rollingTotal);
        $this->assertSame(3275, $rollingTotal[0]);
    }

    public function testLedgerZScoresFindTheLargeTransaction(): void
    {
        $selector = static fn (Receipt $receipt): int => $receipt->getSubtotalCents();
        $scores = $this->ledger->zScore($selector);
        $outliers = $this->ledger->outliers($selector, 3);

        $this->assertCount(200, $scores);
        $this->assertEqualsWithDelta(11.344460109855381, $scores[199], 0.000000000001);
        $this->assertSame(
            ['R-074', 'R-150', 'R-200'],
            array_map(static fn (Receipt $receipt): string => $receipt->ReceiptNumber, $outliers)
        );
    }
}

class Receipt
{
    public string $ReceiptNumber;
    public string $JurisdictionCode;
    public int $ItemCount;
    public int $OccurredAt;
    public int $ProcessingMilliseconds;

    protected int $subtotalCents;
    protected int $taxCents;

    public function __construct(
        string $receiptNumber,
        int $subtotalCents,
        TaxJurisdiction $jurisdiction,
        int $itemCount,
        int $occurredAt,
        int $processingMilliseconds
    ) {
        $this->ReceiptNumber = $receiptNumber;
        $this->JurisdictionCode = $jurisdiction->getCode();
        $this->ItemCount = $itemCount;
        $this->OccurredAt = $occurredAt;
        $this->ProcessingMilliseconds = $processingMilliseconds;
        $this->subtotalCents = $subtotalCents;
        $this->taxCents = $jurisdiction->calculateTax($subtotalCents);
    }

    public function getSubtotalCents(): int
    {
        return $this->subtotalCents;
    }

    public function getTaxCents(): int
    {
        return $this->taxCents;
    }

    public function getTotalCents(): int
    {
        return $this->subtotalCents + $this->taxCents;
    }
}

class TaxJurisdiction
{
    protected string $code;
    protected int $rateInThousandthsOfPercent;

    public function __construct(string $code, int $rateInThousandthsOfPercent)
    {
        $this->code = $code;
        $this->rateInThousandthsOfPercent = $rateInThousandthsOfPercent;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function calculateTax(int $subtotalCents): int
    {
        $tax = $subtotalCents * $this->rateInThousandthsOfPercent;
        $rounding = $tax < 0 ? -50000 : 50000;

        return intdiv($tax + $rounding, 100000);
    }
}
