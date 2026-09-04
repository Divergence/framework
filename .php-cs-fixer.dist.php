<?php

$header = <<<EOF
This file is part of the Divergence package.

(c) Henry Paradiz <henry.paradiz@gmail.com>

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
EOF;

$headerRule = [
    'header' => $header,
    'validator' => '/' . preg_quote($header, '/') . '(?P<EXTRA>.*)??/s',
    'comment_type' => 'PHPDoc',
    'location' => 'after_open',
    'separate' => 'none',
];

$headerPolicy = new class($headerRule) implements PhpCsFixer\Config\RuleCustomisationPolicyInterface {
    private array $headerRule;
    private string $headerPrefix;

    public function __construct(array $headerRule)
    {
        $this->headerRule = $headerRule;
        $this->headerPrefix = "<?php\n/**\n" . implode("\n", array_map(
            static fn (string $line): string => rtrim(' * ' . $line),
            explode("\n", $headerRule['header'])
        )) . "\n";
    }

    public function getPolicyVersionForCache(): string
    {
        return 'preserve-header-separation-v3';
    }

    public function getRuleCustomisers(): array
    {
        return [
            'header_comment' => $this->customizeHeaderComment(...),
        ];
    }

    private function customizeHeaderComment(SplFileInfo $file): bool|PhpCsFixer\Fixer\FixerInterface
    {
        $contents = file_get_contents($file->getPathname());

        if (false !== $contents && str_starts_with(str_replace("\r", '', $contents), $this->headerPrefix)) {
            return false;
        }

        $headerRule = $this->headerRule;

        if (false !== $contents && preg_match('/\A<\?php\R(?<doc>\/\*\*.*?\*\/)(?<separator>\R*)/s', $contents, $matches)) {
            $lines = array_slice(explode("\n", str_replace("\r", '', $matches['doc'])), 1, -1);
            $extra = implode("\n", array_map(
                static fn (string $line): string => ' *' === $line ? '' : (str_starts_with($line, ' * ') ? substr($line, 3) : $line),
                $lines
            ));

            if ('' !== trim($extra)) {
                $headerRule['header'] .= "\n\n" . $extra;
            }

            if (1 < substr_count(str_replace("\r", '', $matches['separator']), "\n")) {
                $headerRule['separate'] = 'bottom';
            }
        }

        $fixer = new PhpCsFixer\Fixer\Comment\HeaderCommentFixer();
        $fixer->configure($headerRule);

        return $fixer;
    }
};

$finder = (new PhpCsFixer\Finder())
    //->exclude('somedir')
    ->in(__DIR__)
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR1' => true,
        '@PSR2' => true,
        'no_break_comment' => false,
        'array_syntax' => ['syntax' => 'short'],
        'trailing_comma_in_multiline' => ['elements' => ['arrays']],
        'no_trailing_comma_in_singleline' => true,
        'ternary_operator_spaces' => true,
        'trim_array_spaces' => true,
        'indentation_type' => true,
        'header_comment' => $headerRule,
    ])
    ->setRuleCustomisationPolicy($headerPolicy)
    ->setFinder($finder)   
;
