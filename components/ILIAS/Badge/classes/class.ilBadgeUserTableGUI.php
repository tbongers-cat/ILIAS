<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\Badge;

use ILIAS\UI\Factory;
use ILIAS\UI\URLBuilder;
use ILIAS\Data\Order;
use ILIAS\Data\Range;
use ilLanguage;
use ilGlobalTemplateInterface;
use ILIAS\UI\Renderer;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\RequestInterface;
use ILIAS\UI\Component\Table\DataRowBuilder;
use Generator;
use ILIAS\UI\Component\Table\DataRetrieval;
use ILIAS\DI\Container;
use ilBadgeHandler;
use ilObject;
use ilBadge;
use ilBadgeAssignment;
use ilUserQuery;
use DateTimeImmutable;

class ilBadgeUserTableGUI
{
    private readonly Factory $factory;
    private readonly Renderer $renderer;
    private readonly ServerRequestInterface|RequestInterface $request;
    private readonly int $parent_ref_id;
    private readonly ?ilBadge $award_badge;
    private readonly ilLanguage $lng;
    private readonly ilGlobalTemplateInterface $tpl;

    public function __construct(int $parent_ref_id, ?ilBadge $award_badge = null)
    {
        global $DIC;

        $this->lng = $DIC->language();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->request = $DIC->http()->request();
        $this->parent_ref_id = $parent_ref_id;
        $this->award_badge = $award_badge;
    }

    private function buildDataRetrievalObject(
        Factory $f,
        Renderer $r,
        int $parent_ref_id,
        ?ilBadge $award_badge = null
    ): DataRetrieval {
        return new class ($f, $r, $parent_ref_id, $award_badge) implements DataRetrieval {
            public function __construct(
                private Factory $ui_factory,
                private Renderer $ui_renderer,
                private int $parent_ref_id,
                private ?ilBadge $award_badge = null
            ) {
            }

            /**
             * TODO gvollbach: Please provide the exaxct shape of the list of arrays
             * @return array<string,string>
             */
            private function getBadgeImageTemplates(Container $DIC): array
            {
                $a_parent_obj_id = null;
                $assignments = null;
                $user_ids = null;
                $parent_ref_id = $this->parent_ref_id;
                $a_restrict_badge_id = 0;
                $data = [];
                $badges = [];
                $tree = $DIC->repositoryTree();

                if (!$a_parent_obj_id) {// TODO gvollbach: $a_parent_obj_id is always null
                    $a_parent_obj_id = ilObject::_lookupObjId($parent_ref_id);
                }

                if ($parent_ref_id) {
                    $user_ids = ilBadgeHandler::getInstance()->getUserIds($parent_ref_id, $a_parent_obj_id);
                }

                $obj_ids = [$a_parent_obj_id];

                foreach ($tree->getSubTree($tree->getNodeData($parent_ref_id)) as $node) {
                    $obj_ids[] = (int) $node['obj_id'];
                }

                foreach ($obj_ids as $obj_id) {
                    foreach (ilBadge::getInstancesByParentId($obj_id) as $badge) {
                        $badges[$badge->getId()] = $badge;
                    }

                    foreach (ilBadgeAssignment::getInstancesByParentId($obj_id) as $ass) {
                        if ($a_restrict_badge_id !== $ass->getBadgeId()) {
                            continue;
                        }

                        $assignments[$ass->getUserId()][] = $ass;
                    }
                }

                if (!$user_ids && $assignments !== null) {
                    $user_ids = array_keys($assignments);
                }

                $tmp['set'] = [];
                if (count($user_ids) > 0) {
                    $uquery = new ilUserQuery();
                    $uquery->setLimit(9999);
                    $uquery->setUserFilter($user_ids);
                    $tmp = $uquery->query();
                }
                foreach ($tmp['set'] as $user) {
                    if (is_array($assignments) && array_key_exists($user['usr_id'], $assignments)) {
                        foreach ($assignments[$user['usr_id']] as $user_ass) {
                            $idx = $user_ass->getBadgeId() . '-' . $user['usr_id'];
                            $badge = $badges[$user_ass->getBadgeId()];
                            $parent = $badge->getParentMeta();
                            $timestamp = $user_ass->getTimestamp();
                            $immutable = new DateTimeImmutable();
                            $user_id = $user['usr_id'];
                            $name = $user['lastname'] . ', ' . $user['firstname'];
                            $login = $user['login'];
                            $type = ilBadge::getExtendedTypeCaption($badge->getTypeInstance());
                            $title = $badge->getTitle();
                            $issued = $immutable->setTimestamp($timestamp);
                            $parent_id = $parent['id'] ?? 0; //TODO gvollbach: According to mit SCA the offset "id" always exists
                            $data[$idx] = [
                                'user_id' => $user_id,
                                'name' => $name,
                                'login' => $login,
                                'type' => $type,
                                'title' => $title,
                                'issued' => $issued,
                                'parent_id' => $parent_id,
                                'parent_meta' => $parent
                            ];
                        }
                    } elseif ($this->award_badge) {
                        $idx = '0-' . $user['usr_id'];
                        $user_id = $user['usr_id'];
                        $name = $user['lastname'] . ', ' . $user['firstname'];
                        $login = $user['login'];
                        $data[$idx] = [
                            'user_id' => $user_id,
                            'name' => $name,
                            'login' => $login,
                            'type' => '',
                            'title' => '',
                            'issued' => '',
                            'parent_id' => ''
                        ];
                    }
                }

                return $data;
            }

            public function getRows(
                DataRowBuilder $row_builder,
                array $visible_column_ids,
                Range $range,
                Order $order,
                ?array $filter_data,
                ?array $additional_parameters
            ): Generator {
                $records = $this->getRecords($range, $order);
                foreach ($records as $idx => $record) {
                    if (isset($idx)) {// TODO gvollbach: Why do we have to check the index agains NULL?
                        $row_id = (string) $idx;
                        yield $row_builder->buildDataRow($row_id, $record);
                    }
                }
            }

            public function getTotalRowCount(
                ?array $filter_data,
                ?array $additional_parameters
            ): ?int {
                return count($this->getRecords());
            }

            /**
             * TODO gvollbach: Please provide the exaxct shape of the list of arrays
             */
            private function getRecords(Range $range = null, Order $order = null): array
            {
                global $DIC;

                $data = $this->getBadgeImageTemplates($DIC);

                if ($order) {
                    [$order_field, $order_direction] = $order->join(
                        [],
                        fn($ret, $key, $value) => [$key, $value]
                    );
                    usort($data, static fn($a, $b) => $a[$order_field] <=> $b[$order_field]);
                    if ($order_direction === 'DESC') {
                        $data = array_reverse($data);
                    }
                }
                if ($range) {
                    $data = array_slice($data, $range->getStart(), $range->getLength());
                }

                return $data;
            }
        };
    }

    public function renderTable(): void
    {
        $f = $this->factory;
        $r = $this->renderer;
        $request = $this->request;
        $df = new \ILIAS\Data\Factory();

        $columns = [
            'name' => $f->table()->column()->text($this->lng->txt('name')),
            'login' => $f->table()->column()->text($this->lng->txt('login')),
            'type' => $f->table()->column()->text($this->lng->txt('type')),
            'title' => $f->table()->column()->text($this->lng->txt('title')),
            'issued' => $f->table()->column()->date(
                $this->lng->txt('badge_issued_on'),
                $df->dateFormat()->germanShort()
            )
        ];

        $table_uri = $df->uri($request->getUri()->__toString());
        $url_builder = new URLBuilder($table_uri);
        $query_params_namespace = ['tid'];

        [$url_builder, $action_parameter_token, $row_id_token] =
            $url_builder->acquireParameters(
                $query_params_namespace,
                'table_action',
                'id',
            );

        $data_retrieval = $this->buildDataRetrievalObject($f, $r, $this->parent_ref_id, $this->award_badge);

        $table = $f->table()
                   ->data('', $columns, $data_retrieval)
                   ->withRequest($request);

        $out = [$table];
        $this->tpl->setContent($r->render($out));
    }
}
