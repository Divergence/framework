<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Controllers\Records\Endpoints;

use Divergence\Controllers\Records\AbstractRecordsEndpoint;
use Divergence\Controllers\RecordsRequestHandler;
use Divergence\IO\Database\Connections;
use Psr\Http\Message\ResponseInterface;

class Browse extends AbstractRecordsEndpoint
{
    protected RecordsRequestHandler $handler;

    public function __construct(RecordsRequestHandler $handler)
    {
        $this->handler = $handler;
    }

    public function handle(...$arguments): ResponseInterface
    {
        [$options, $conditions, $responseID, $responseData] = array_pad($arguments, 4, null);
        $conditions ??= [];
        $responseData ??= [];

        if (!$this->handler->checkBrowseAccess($arguments)) {
            return $this->handler->throwUnauthorizedError();
        }

        $conditions = $this->prepareBrowseConditions($conditions);
        $options = $this->prepareDefaultBrowseOptions();

        if (!empty($_REQUEST['sort'])) {
            $sort = json_decode($_REQUEST['sort'], true);

            if (!$sort || !is_array($sort)) {
                return $this->handler->respond('error', [
                    'success' => false,
                    'failed' => [
                        'errors' => 'Invalid sorter.',
                    ],
                ]);
            }

            foreach ($sort as $field) {
                $options['order'][$field['property']] = $field['direction'];
            }
        }

        if (!empty($_REQUEST['filter'])) {
            $filter = json_decode($_REQUEST['filter'], true);

            if (!$filter || !is_array($filter)) {
                return $this->handler->respond('error', [
                    'success' => false,
                    'failed' => [
                        'errors' => 'Invalid filter.',
                    ],
                ]);
            }

            foreach ($filter as $field) {
                $conditions[$field['property']] = $field['value'];
            }
        }

        $className = $this->handler::$recordClass;
        $storageClass = Connections::getConnectionType();

        return $this->handler->respond(
            $responseID ?: $this->handler->getTemplateName($className::getPluralNoun()),
            array_merge($responseData, [
                'success' => true,
                'data' => $className::getAllByWhere($conditions, $options),
                'conditions' => $conditions,
                'total' => $storageClass::foundRows(),
                'limit' => $options['limit'],
                'offset' => $options['offset'],
            ])
        );
    }

    protected function prepareBrowseConditions($conditions = [])
    {
        if ($this->handler->browseConditions) {
            if (!is_array($this->handler->browseConditions)) {
                $this->handler->browseConditions = [$this->handler->browseConditions];
            }

            $conditions = array_merge($this->handler->browseConditions, $conditions);
        }

        return $conditions;
    }

    protected function prepareDefaultBrowseOptions(): array
    {
        if (!isset($_REQUEST['offset']) && isset($_REQUEST['start']) && is_numeric($_REQUEST['start'])) {
            $_REQUEST['offset'] = $_REQUEST['start'];
        }

        return [
            'limit' => !empty($_REQUEST['limit']) && is_numeric($_REQUEST['limit']) ? $_REQUEST['limit'] : $this->handler->browseLimitDefault,
            'offset' => !empty($_REQUEST['offset']) && is_numeric($_REQUEST['offset']) ? $_REQUEST['offset'] : false,
            'order' => $this->handler->browseOrder,
        ];
    }
}
