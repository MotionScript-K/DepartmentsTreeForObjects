<?php

/**
 * Если Управляющий или Заместитель управляющего или Директор объекта
 */
elseif ($userService->getCurrent()->isUo() || $userService->getCurrent()->isZuo()) {
    $departmentService = Container::getDepartmentService();
    $user = $userService->getCurrent();

    // Получаем массив ID объектов
    $departments = [];
    if (!empty($user['UF_DEPARTMENT'])) {
        $departments[] = $user['UF_DEPARTMENT'];
    }
    if (!empty($user['UF_FUNC_DEPARTMENT'])) {
        $departments[] = $user['UF_FUNC_DEPARTMENT'];
    }

    // Совместительство
    if ($user->isPtUo() || $user->isPtZuo()) {
        foreach ($user['UF_INT_PT_DEPARTMENT'] as $depId) {
            $departments[] = $depId;
        }
    }

    // Формируем и собираем массив ID
    $flatDepartments = [];
    foreach ($departments as $dep) {
        if (is_array($dep)) {
            $flatDepartments = array_merge($flatDepartments, array_map('intval', $dep));
        } else {
            $flatDepartments[] = (int)$dep;
        }
    }

    // Получаем объекты по ID
    $objectDepartments = $departmentService->getRepo()->query()
        ->wherePrimary('=', $flatDepartments)
        ->all();

    // Массив для формы
    $tdNames = [];
    foreach ($objectDepartments as $object) {
        if ($td = $object->getTdParent()) {
            $tdNames[$td->getId()] = $td->getName();
        }
    }

    // дерево доступных подразделений
    $usersId = $user->getId();
    $tree = $departmentService->getDepartmentsTreeForObjects($usersId, $flatDepartments);
    $treeArray = $tree->toArray();


    // Заполнение значений в форме
    $form->getField('PROPERTY_DEPARTMENT1_VALUE')->setItems($tdNames);

    foreach ($treeArray as $rootId => $rootNode) {
        if ($level1 = $treeArray[$rootNode['VALUE']]) {
            $form->loadValues(['PROPERTY_DEPARTMENT1_VALUE' => $level1['VALUE']]);
        }

        if ($level1) {
            foreach ($level1['CHILDS'] as $childDepartment => $childNode) {
                if ($level2 = $treeArray[$childNode['VALUE']]) {
                    $form->loadValues(['PROPERTY_DEPARTMENT2_VALUE' => $level2['VALUE']]);
                }
                break;
            }
            if ($level2) {
                foreach($level2['CHILDS'] as $groupObjects => $groupNode) {
                    if ($level3 = $treeArray[$groupNode['VALUE']]) {
                        $form->loadValues(['PROPERTY_DEPARTMENT3_VALUE' => $level3['VALUE']]);
                    }
                    break;
                }
            }
            if ($level3) {
                foreach($level3['CHILDS'] as $objectName => $objectNode) {
                    if ($level4 = $treeArray[$objectNode['VALUE']]) {
                        $form->loadValues(['PROPERTY_DEPARTMENT4_VALUE' => $level4['VALUE']]);
                    }
                    break;
                }
            }
            break;
        }
    }
}
