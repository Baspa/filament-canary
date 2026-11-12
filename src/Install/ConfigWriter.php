<?php

namespace Baspa\FilamentCanary\Install;

use LogicException;

class ConfigWriter
{
    public function __construct(
        protected string $publishedConfig = __DIR__.'/../../config/filament-canary.php',
    ) {}

    /**
     * Build the full config/filament-canary.php contents: the package's published config,
     * with acting_as / tenant pointing at the generated resolver classes and the given
     * settings in place of their defaults.
     *
     * @param  list<ResolverClass>  $actingAs
     * @param  list<ResolverClass>  $tenants
     * @param  array<string, mixed>  $settings  exportable values (see exportable()) for the other options
     */
    public function generate(array $actingAs, array $tenants, array $settings = []): string
    {
        $source = (string) file_get_contents($this->publishedConfig);
        $defaults = $this->defaults();

        $values = [
            'acting_as' => $this->block($actingAs),
            'tenant' => $this->block($tenants),
        ];

        foreach ($settings as $key => $value) {
            if (! isset($values[$key]) && array_key_exists($key, $defaults) && $value !== $defaults[$key]) {
                $values[$key] = $this->export($value);
            }
        }

        $ranges = $this->valueRanges($source);

        foreach (array_keys($values) as $key) {
            if (! isset($ranges[$key])) {
                throw new LogicException("Could not find the [{$key}] option in {$this->publishedConfig} to fill in.");
            }
        }

        // Replace from the end of the file so the offsets of earlier values stay valid.
        uksort($values, fn (string $a, string $b) => $ranges[$b][0] <=> $ranges[$a][0]);

        foreach ($values as $key => $php) {
            [$offset, $length] = $ranges[$key];
            $source = substr_replace($source, $php, $offset, $length);
        }

        return $source;
    }

    /**
     * The options in the published config with their default values.
     *
     * @return array<mixed>
     */
    public function defaults(): array
    {
        $defaults = require $this->publishedConfig;

        return is_array($defaults) ? $defaults : [];
    }

    /**
     * Whether a value can be written back into the config file: a scalar, null, or an
     * array of those (nested arrays included).
     */
    public static function exportable(mixed $value): bool
    {
        if (! is_array($value)) {
            return $value === null || is_scalar($value);
        }

        foreach ($value as $item) {
            if (! self::exportable($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<ResolverClass>  $classes
     */
    public function block(array $classes): string
    {
        if ($classes === []) {
            return 'null';
        }

        $entries = array_map(
            fn (ResolverClass $class) => "        // {$class->panelId} — {$class->note}\n"
                ."        '{$class->panelId}' => \\{$class->className()}::class,",
            $classes,
        );

        return "[\n".implode("\n", $entries)."\n    ]";
    }

    protected function export(mixed $value, int $depth = 1): string
    {
        if (! is_array($value)) {
            return match (true) {
                $value === null => 'null',
                is_bool($value) => $value ? 'true' : 'false',
                default => var_export($value, true),
            };
        }

        if ($value === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $depth + 1);
        $lines = [];

        foreach ($value as $key => $item) {
            $prefix = array_is_list($value) ? '' : var_export($key, true).' => ';
            $lines[] = $indent.$prefix.$this->export($item, $depth + 1).',';
        }

        return "[\n".implode("\n", $lines)."\n".str_repeat('    ', $depth).']';
    }

    /**
     * Where each option's value sits in the published config source. Options are indented
     * four spaces, and a multi-line array value closes on its own line at that indent.
     *
     * @return array<string, array{int, int}> option => [offset, length]
     */
    protected function valueRanges(string $source): array
    {
        $option = '/^ {4}\'(\w+)\' => (\[\r?\n.*?\n {4}\]|[^\r\n]*),\r?$/ms';

        preg_match_all($option, $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $ranges = [];

        foreach ($matches as $match) {
            $ranges[$match[1][0]] = [$match[2][1], strlen($match[2][0])];
        }

        return $ranges;
    }
}
