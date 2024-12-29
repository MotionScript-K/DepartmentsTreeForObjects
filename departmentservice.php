<?php

namespace Idex\Core\Struct;

use Bitrix\Iblock\Model\Section;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\Date;
use Bitrix\Main\Type\DateTime;
use CUser;
use Idex\Core\Container;
use Idex\Core\Exceptions\ModelNotFoundException;
use Idex\Core\ORM\Entities\Tables\AreaResponsibilityDppTable;
use Idex\Core\ORM\Generated\Base\BaseIblockSectionModel;
use Idex\Core\ORM\Models\Iblock\Structure\Departments\IblockStructureDepartmentsSectionModel;
use Idex\Core\ORM\Models\Iblock\Structure\Departments\IblockStructureDepartmentsSectionModelCollection;
use Idex\Core\ORM\Models\Iblock\Structure\FunctionalDepartments\IblockStructureFunctionalDepartmentsSectionModel;
use Idex\Core\ORM\Models\Iblock\Structure\FunctionalDepartments\IblockStructureFunctionalDepartmentsSectionModelCollection;
use Idex\Core\ORM\Models\UserModel;
use Idex\Core\ORM\Repositories\Iblock\Structure\Departments\IblockStructureDepartmentsSectionRepository;
use Idex\Core\ORM\Repositories\Iblock\Structure\FunctionalDepartments\IblockStructureFunctionalDepartmentsSectionRepository;
use Idex\Core\Struct\Tree\Tree;
use Idex\Core\Vacation\DepartmentRelation\RopService;

/**
 * Сервис для работы с подразделениями
 */
class DepartmentService
{
    const HR_DEPARTMENT_XML_ID = '******'; // Отдел кадрового делопроизводства
    const SVA_DEPARTMENT_XML_ID = '******'; // Служба внутреннего анализа
    const OVA_SPEC_PROJECTS_DEPARTMENT_XML_ID = '******'; // Отдел внутреннего анализа специальных проектов
    const DEPTH_LEVEL_OBJECT = 5;

    const TD_DEPTH_LEVEL = 2;
    const FILIAL_DEPTH_LEVEL = 3;
    const GROUP_DEPTH_LEVEL = 4;
    const OBJECT_DEPTH_LEVEL = 5;

    const OBJECT_KBK_STR_LENGTH = 9;

    const OBJECT_GROUP_KBK = '/^[0-9]\-[0-9]{2}\-ГО[0-9]+$/i';
    const FILIAL_KBK = '/^[0-9]\-([0-9][1-9]+|[1-9][0-9]+)-000$/';
    const TD_KBK = '/^[0-9]\-00\-000$/';
    const OBJECT_KBK = '/О$/';

    /**
     * @var IblockStructureDepartmentsSectionModel|null
     */
    private ?IblockStructureDepartmentsSectionModel $_hr_department = null;

    private ?IblockStructureDepartmentsSectionModel $_hr_department_office = null;

    /**
     * @var IblockStructureDepartmentsSectionRepository
     */
    protected $departmentRepo;
    /**
     * @var IblockStructureFunctionalDepartmentsSectionRepository
     */
    protected $functionalDepartmentRepo;

    /**
     * @param IblockStructureDepartmentsSectionRepository $departmentRepo
     */
    public function __construct(
        IblockStructureFunctionalDepartmentsSectionRepository $functionalDepartmentRepo,
        IblockStructureDepartmentsSectionRepository $departmentRepo
    ) {
        Loader::includeModule('iblock');

        $this->functionalDepartmentRepo = $functionalDepartmentRepo;
        $this->departmentRepo = $departmentRepo;
    }

    /**
     * Репа подразделений
     *
     * @return IblockStructureDepartmentsSectionRepository
     */
    public function getRepo(): IblockStructureDepartmentsSectionRepository
    {
        return $this->departmentRepo;
    }

    /**
     * Репа функциональных подразделений
     *
     * @return IblockStructureFunctionalDepartmentsSectionRepository
     */
    public function getFunctionalRepo(): IblockStructureFunctionalDepartmentsSectionRepository
    {
        return $this->functionalDepartmentRepo;
    }
  
    /**
     * Древовидная структура доступных подразделений для объектов
     * @param int $userId
     * @param array $objectIds
     * @return Tree
     */
    public function getDepartmentsTreeForObjects(
        int $userId,
        array $objectIds
    ): Tree {

        if (empty($objectIds)) {
            return new Tree([], 'ID', 'NAME');
        }

        //Получаем подразделения по объектам
        $objectDepartments = $this->departmentRepo->all([
            'filter' => ['ID' => $objectIds],
        ]);

        $departments = [];
        foreach ($objectDepartments as $object) {
            // Родительский департамент
            if ($parent = $object->getParent()) {
                $departments[] = $parent->getId();
            }
            // Филиал
            if ($filial = $object->getFilialParent()) {
                $departments[] = $filial->getId();
            }
            // ТД
            if ($td = $object->getTdParent()) {
                $departments[] = $td->getId();
            }
            // Сам объект
            $departments[] = $object->getId();
        }

        // Получаем модели для дерева
        $models = $this->departmentRepo->all([
            'filter' => ['ID' => $departments, '=ACTIVE' => 'Y'],
            'order' => ['LEFT_MARGIN' => 'asc'],
        ]);

        // Формируем
        if ($models) {
            $tree = new Tree($models->toArray(), 'ID', 'NAME');

            foreach ($models as $model) {
                $childDepartments = $this->departmentRepo->all([
                    'select' => ['ID', 'NAME', 'IBLOCK_SECTION_ID'],
                    'filter' => [
                        'ID' => $departments,
                        '=ACTIVE' => 'Y',
                        '>LEFT_MARGIN' => $model['LEFT_MARGIN'],
                        '<RIGHT_MARGIN' => $model['RIGHT_MARGIN'],
                    ],
                    'order' => ['LEFT_MARGIN' => 'asc'],
                ]);

                foreach ($childDepartments as $child) {
                    $tree->addNode(
                        $child['ID'],
                        $child['NAME'],
                        $child['IBLOCK_SECTION_ID']
                    );
                }
            }
        } else {
            $tree = new Tree([], 'ID', 'NAME');
        }

        return $tree;
    }
}
