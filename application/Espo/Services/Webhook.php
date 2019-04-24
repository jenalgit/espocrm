<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\Services;

use \Espo\ORM\Entity;

class Webhook extends Record
{
    protected $eventTypeList = [
        "create",
        "update",
        "delete",
        "fieldUpdate",
    ];

    protected $onlyAdminAttributeList = ['userId', 'userName'];

    public function populateDefaults(Entity $entity, $data)
    {
        parent::populateDefaults($entity, $data);

        if ($this->getUser()->isApi()) {
            $entity->set('userId', $this->getUser()->id);
        }
    }

    protected function filterUpdateInput($data)
    {
        if (!$this->getUser()->isAdmin()) {
            unset($data->event);
            unset($data->entityType);
            unset($data->field);
        }
    }

    protected function beforeCreateEntity(Entity $entity, $data)
    {
        $this->checkEntityUserIsApi($entity);
        $this->checkEntityEvent($entity);
    }

    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        $this->checkEntityUserIsApi($entity);
        $this->checkEntityEvent($entity);
    }

    protected function checkEntityUserIsApi(Entity $entity)
    {
        $userId = $entity->get('userId');
        if (!$userId) return;

        $user = $this->getEntityManager()->getEntity('User', $userId);
        if (!$user || !$user->isApi()) throw new Forbidden("User must be an API User.");
    }

    protected function checkEntityEvent(Entity $entity)
    {
        $event = $entity->get('event');
        if (!$event) throw new Forbidden("Event is empty.");

        $arr = explode('.', $event);
        if (count($arr) !== 2) throw new Forbidden("Not supported event.");
        list($entityType, $type) = explode('.', $event);

        if ($entityType === 'Record') {
            $entityType = $entity->get('entityType');
        }

        if (!$entityType) throw new Forbidden("Entity Type is empty.");
        if (!$this->getEntityManager()->hasRepository($entityType)) throw new Forbidden("Not existing Entity Type.");
        if (!$this->getAcl()->checkScope($entityType, 'read')) throw new Forbidden("Entity Type is forbidden.");

        if (!in_array($type, $this->eventTypeList)) throw new Forbidden("Not supported event.");

        if ($type === 'fieldUpdate') {
            $field = $entity->get('field');
            if (!$field) throw new Forbidden("Field is empty.");
            $forbiddenFieldList = $this->getAcl()->getScopeForbiddenFieldList($entityType);
            if (in_array($field, $forbiddenFieldList)) throw new Forbidden("Field is forbidden.");
        }
    }
}
