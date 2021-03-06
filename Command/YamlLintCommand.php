<?php

declare(strict_types=1);

/**
 * This file is part of Narrowspark Framework.
 *
 * (c) Daniel Bannert <d.bannert@anolilab.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Viserio\Component\Parser\Command;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

/**
 * Validates Yaml files syntax and outputs encountered errors.
 *
 * Some of this code has been ported from Symfony. The original
 *
 * @see https://github.com/symfony/symfony/blob/master/src/Symfony/Component/Yaml/Command/LintCommand.php
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 */
class YamlLintCommand extends AbstractLintCommand
{
    /**
     * {@inheritdoc}
     */
    protected static $defaultName = 'lint:yaml';

    /**
     * {@inheritdoc}
     */
    protected $signature = 'lint:yaml
        [filename : A file or a directory or STDIN.]
        [--format=txt : The output format. Supports `txt` or `json`.]
        [--parse-tags= : Parse custom tags.]
    ';

    /**
     * {@inheritdoc}
     */
    protected $description = 'Lints a Yaml file and outputs encountered errors.';

    /**
     * A Parser instance.
     *
     * @var \Symfony\Component\Yaml\Parser
     */
    private $parser;

    /**
     * {@inheritdoc}
     */
    protected function validate(string $content, ?string $file = null): array
    {
        $prevErrorHandler = \set_error_handler(function (int $level, string $message, string $file, int $line) use (&$prevErrorHandler): bool {
            if ($level === \E_USER_DEPRECATED) {
                throw new ParseException($message, $this->getParser()->getRealCurrentLineNb() + 1);
            }

            return $prevErrorHandler !== null ? $prevErrorHandler($level, $message, $file, $line) : false;
        });

        $flags = $this->option('parse-tags') !== null ? Yaml::PARSE_CUSTOM_TAGS : 0;

        try {
            $this->getParser()->parse($content, Yaml::PARSE_CONSTANT | $flags);
        } catch (ParseException $e) {
            return ['file' => $file, 'line' => $e->getParsedLine(), 'valid' => false, 'message' => $e->getMessage()];
        } finally {
            \restore_error_handler();
        }

        return ['file' => $file, 'valid' => true];
    }

    /**
     * {@inheritdoc}
     */
    protected function displayTxt(array $filesInfo, bool $displayCorrectFiles): int
    {
        $countFiles = \count($filesInfo);
        $erroredFiles = 0;
        $output = $this->getOutput();

        foreach ($filesInfo as $info) {
            /** @var bool $valid */
            $valid = $info['valid'];

            if ($displayCorrectFiles && $valid) {
                $output->comment('<info>OK</info>' . (\is_string($info['file']) ? \sprintf(' in %s', $info['file']) : ''));
            } elseif (! $valid) {
                $erroredFiles++;

                $output->text('<error>ERROR</error>' . (\is_string($info['file']) ? \sprintf(' in %s', $info['file']) : ''));

                /** @var string $message */
                $message = $info['message'];

                $output->text(\sprintf('<error> >> %s</error>', $message));
            }
        }

        if ($erroredFiles === 0) {
            $output->success(\sprintf('All %d YAML files contain valid syntax.', $countFiles));
        } else {
            $output->warning(\sprintf('%d YAML files have valid syntax and %d contain errors.', $countFiles - $erroredFiles, $erroredFiles));
        }

        return \min($erroredFiles, 1);
    }

    /**
     * Get a parser instance.
     *
     * @return \Symfony\Component\Yaml\Parser
     */
    private function getParser(): Parser
    {
        if ($this->parser === null) {
            $this->parser = new Parser();
        }

        return $this->parser;
    }
}
