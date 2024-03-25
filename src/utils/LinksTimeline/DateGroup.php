<?php

namespace flusio\utils\LinksTimeline;

use flusio\models;

/**
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class DateGroup
{
    public \DateTimeImmutable $date;

    /** @var models\Link[] */
    public array $links = [];

    /** @var array<string, ViaGroup> */
    public array $via_groups = [];

    public function __construct(\DateTimeImmutable $date)
    {
        $this->date = $date;
    }

    public function addLink(models\Link $link): void
    {
        $this->links[] = $link;

        if ($link->via_type) {
            $via_key = $link->via_type . '#' . $link->via_resource_id;
            if (isset($this->via_groups[$via_key])) {
                $via_group = $this->via_groups[$via_key];
            } else {
                $via_resource = $link->viaResource();

                assert($via_resource !== null);

                $via_group = new ViaGroup($via_resource);
                $this->via_groups[$via_key] = $via_group;
            }

            $via_group->addLink($link);
        }
    }

    public function isToday(): bool
    {
        $today = \Minz\Time::now();
        return $this->date->format('Y-m-d') === $today->format('Y-m-d');
    }

    public function isYesterday(): bool
    {
        $yesterday = \Minz\Time::ago(1, 'day');
        return $this->date->format('Y-m-d') === $yesterday->format('Y-m-d');
    }
}
