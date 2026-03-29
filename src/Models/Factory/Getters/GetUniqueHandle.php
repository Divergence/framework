<?php

namespace Divergence\Models\Factory\Getters;

class GetUniqueHandle extends ModelGetter
{
    public function getUniqueHandle($text, $options = [])
    {
        $options = $this->prepareOptions($options, [
            'handleField' => $this->getHandleFieldName(),
            'domainConstraints' => [],
            'alwaysSuffix' => false,
            'format' => '%s:%u',
        ]);

        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);

        $handle = $strippedText = preg_replace(
            ['/\s+/', '/_*[^a-zA-Z0-9\-_:]+_*/', '/:[-_]/', '/^[-_]+/', '/[-_]+$/'],
            ['_', '-', ':', '', ''],
            trim($text)
        );

        $handle = trim($handle, '-_');

        $incarnation = 0;
        do {
            $incarnation++;

            if ($options['alwaysSuffix'] || $incarnation > 1) {
                $handle = sprintf($options['format'], $strippedText, $incarnation);
            }
        } while ($this->factory->getByWhere(array_merge($options['domainConstraints'], [$options['handleField'] => $handle])));

        return $handle;
    }
}
