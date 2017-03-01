<?php

namespace Croogo\Acl\Model\Table;

use Cake\Cache\Cache;
use Cake\Utility\Hash;
use Cake\ORM\TableRegistry;
use Cake\Core\Configure;

/**
 * AclPermission Model
 *
 * @category Model
 * @package  Croogo.Acl.Model
 * @version  1.0
 * @author   Fahad Ibnay Heylaal <contact@fahad19.com>
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link     http://www.croogo.org
 */
class PermissionsTable extends \Acl\Model\Table\PermissionsTable
{

/**
 * afterSave
 */
    public function afterSave($created, $options = [])
    {
        Cache::clearGroup('acl', 'permissions');
    }

/**
 * Generate allowed actions for current logged in Role
 *
 * @param int$roleId
 * @return array of elements formatted like ControllerName/action_name
 */
    public function getAllowedActionsByRoleId($roleId)
    {
        $aroIds = $this->Aro->node([
            'model' => 'Roles',
            'foreign_key' => $roleId,
        ])->extract('id')->toArray();
        if (empty($aroIds[0])) {
            return [];
        }
        $aroId = $aroIds[0];

        $permissionsForCurrentRole = $this->find('list', [
            'conditions' => [
                'Permissions.aro_id' => $aroId,
                'Permissions._create' => 1,
                'Permissions._read' => 1,
                'Permissions._update' => 1,
                'Permissions._delete' => 1,
            ],
            'fields' => [
                'Permissions.id',
                'Permissions.aco_id',
            ],
            'keyField' => 'id',
            'valueField' => 'aco_id',
        ])->toArray();
        $permissionsByActions = [];
        foreach ($permissionsForCurrentRole as $acoId) {
            $pathQuery = $this->Aco->find('path', ['for' => $acoId]);
            if (!$pathQuery) {
                continue;
            }
            $path = join('/', $pathQuery->extract('alias')->toArray());
            $permissionsByActions[] = $path;
        }

        return $permissionsByActions;
    }

/**
 * Generate allowed actions for current logged in User
 *
 * @param int$userId
 * @return array of elements formatted like ControllerName/action_name
 */
    public function getAllowedActionsByUserId($userId)
    {
        $aroIds = $this->Aro->node([
            'model' => 'Users',
            'foreign_key' => $userId,
        ])->extract('id')->toArray();
        if (empty($aroIds[0])) {
            return [];
        }
        $aroId = $aroIds[0];

        if (Configure::read('Access Control.multiRole')) {
            $RolesUser = TableRegistry::get('Croogo/Users.RolesUser');
            $rolesAro = $RolesUser->getRolesAro($userId);
            $aroIds = array_unique(Hash::merge($aroIds, $rolesAro));
        }

        $permissionsForCurrentUser = $this->find('list', [
            'conditions' => [
                'Permissions.aro_id IN' => $aroIds,
                'Permissions._create' => 1,
                'Permissions._read' => 1,
                'Permissions._update' => 1,
                'Permissions._delete' => 1,
            ],
            'fields' => [
                'Permissions.id',
                'Permissions.aco_id',
            ],
            'keyField' => 'id',
            'valueField' => 'aco_id',
        ])->toArray();
        $permissionsByActions = [];
        foreach ($permissionsForCurrentUser as $acoId) {
            $pathQuery = $this->Aco->find('path', ['for' => $acoId]);
            if (!$pathQuery) {
                continue;
            }
            $path = join('/', $pathQuery->extract('alias')->toArray());
            if (!in_array($path, $permissionsByActions)) {
                $permissionsByActions[] = $path;
            }
        }

        return $permissionsByActions;
    }

/**
 * Retrieve an array for formatted aros/aco data
 *
 * @param array $acos
 * @param array $aros
 * @param array $options
 * @return array formatted array
 */
    public function format($acos, $aros, $options = [])
    {
        $options = Hash::merge([
            'model' => 'Roles',
            'perms' => true
        ], $options);
        extract($options);
        $permissions = [];

        foreach ($acos as $aco) {
            $acoId = $aco->id;
            $acoAlias = $aco->alias;

            $path = $this->Acos->find('path', ['for' => $acoId]);
            $path = join('/', collection($path)->extract('alias')->toArray());
            $data = [
                'children' => $this->Acos->childCount($aco, true),
                'depth' => substr_count($path, '/'),
            ];

            foreach ($aros as $aroFk => $aroId) {
                $role = [
                    'model' => $model, 'foreign_key' => $aroFk,
                ];
                if ($perms) {
                    if ($aroFk == 1 || $this->check($role, $path)) {
                        $data['roles'][$aroFk] = 1;
                    } else {
                        $data['roles'][$aroFk] = 0;
                    }
                }
                $permissions[$acoId] = [$acoAlias => $data];
            }

        }
        return $permissions;
    }
}