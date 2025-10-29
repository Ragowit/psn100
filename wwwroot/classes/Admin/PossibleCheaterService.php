<?php

declare(strict_types=1);

require_once __DIR__ . '/PossibleCheaterRuleGroup.php';
require_once __DIR__ . '/PossibleCheaterSectionDefinition.php';
require_once __DIR__ . '/PossibleCheaterReport.php';

class PossibleCheaterService
{
    private PDO $database;

    /**
     * @var PossibleCheaterRuleGroup[]|null
     */
    private ?array $generalRuleGroups = null;

    /**
     * @var PossibleCheaterSectionDefinition[]|null
     */
    private ?array $sectionDefinitions = null;

    private const GENERAL_RULE_GROUPS = [
        [
            'label' => 'Luftrausers',
            'conditions' => [
                'te.np_communication_id = \'NPWR05066_00\' AND te.order_id = 2',
                'te.np_communication_id = \'NPWR05066_00\' AND te.order_id = 9',
            ],
        ],
        [
            'label' => 'Burn Zombie Burn!',
            'conditions' => [
                'te.np_communication_id = \'NPWR00382_00\' AND te.order_id = 19',
                'te.np_communication_id = \'NPWR00382_00\' AND te.order_id = 20',
                'te.np_communication_id = \'NPWR00382_00\' AND te.order_id = 21',
                'te.np_communication_id = \'NPWR00382_00\' AND te.order_id = 22',
            ],
        ],
        [
            'label' => 'Dragon Fin Soup',
            'conditions' => [
                'te.np_communication_id = \'NPWR08208_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR08208_00\' AND te.order_id = 9',
                'te.np_communication_id = \'NPWR08208_00\' AND te.order_id = 10',
                'te.np_communication_id = \'NPWR08208_00\' AND te.order_id = 12',
            ],
        ],
        [
            'label' => 'A-men 2 (PSVITA)',
            'conditions' => [
                'te.np_communication_id = \'NPWR03899_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR03899_00\' AND te.order_id = 10',
            ],
        ],
        [
            'label' => 'A-men 2 (PS3)',
            'conditions' => [
                'te.np_communication_id = \'NPWR04024_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR04024_00\' AND te.order_id = 10',
            ],
        ],
        [
            'label' => 'Hunter\'s Trophy',
            'conditions' => [
                'te.np_communication_id = \'NPWR01472_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR01472_00\' AND te.order_id = 11',
            ],
        ],
        [
            'label' => 'Hunter\'s Trophy 2 - Europa',
            'conditions' => [
                'te.np_communication_id = \'NPWR03558_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR03558_00\' AND te.order_id = 30',
            ],
        ],
        [
            'label' => 'IHF Handball Challenge 14',
            'conditions' => [
                'te.np_communication_id = \'NPWR05839_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR05839_00\' AND te.order_id = 28',
            ],
        ],
        [
            'label' => 'Just Dance 2016',
            'conditions' => [
                'te.np_communication_id = \'NPWR08881_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR08881_00\' AND te.order_id = 25',
                'te.np_communication_id = \'NPWR08881_00\' AND te.order_id = 26',
            ],
        ],
        [
            'label' => 'nail\'d',
            'conditions' => [
                'te.np_communication_id = \'NPWR01064_00\' AND te.order_id = 51',
                'te.np_communication_id = \'NPWR01064_00\' AND te.order_id = 57',
            ],
        ],
        [
            'label' => 'Pinballistik',
            'conditions' => [
                'te.np_communication_id = \'NPWR01685_00\' AND te.order_id = 8',
                'te.np_communication_id = \'NPWR01685_00\' AND te.order_id = 15',
            ],
        ],
        [
            'label' => 'Planet Minigolf',
            'conditions' => [
                'te.np_communication_id = \'NPWR00550_00\' AND te.order_id = 4 AND te.earned_date >= \'2015-01-01\'',
            ],
        ],
        [
            'label' => 'RAMBO THE VIDEO GAME',
            'conditions' => [
                'te.np_communication_id = \'NPWR05256_00\' AND te.order_id = 34',
            ],
        ],
        [
            'label' => 'Rugby League Live 2 - World Cup Edition',
            'conditions' => [
                'te.np_communication_id = \'NPWR05666_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR05666_00\' AND te.order_id = 24',
            ],
        ],
        [
            'label' => 'UFC Personal Trainer',
            'conditions' => [
                'te.np_communication_id = \'NPWR01264_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR01264_00\' AND te.order_id = 44',
            ],
        ],
        [
            'label' => 'Yoostar 2',
            'conditions' => [
                'te.np_communication_id = \'NPWR01267_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR01267_00\' AND te.order_id = 30',
                'te.np_communication_id = \'NPWR01267_00\' AND te.order_id = 31',
                'te.np_communication_id = \'NPWR01267_00\' AND te.order_id = 32',
            ],
        ],
        [
            'label' => 'EA SPORTS FIFA Football',
            'conditions' => [
                'te.np_communication_id = \'NPWR02942_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR02942_00\' AND te.order_id = 22',
            ],
        ],
        [
            'label' => 'GHOSTBUSTERS: The Video Game',
            'conditions' => [
                'te.np_communication_id = \'NPWR00345_00\' AND te.order_id = 41 AND te.earned_date >= \'2012-11-16\'',
                'te.np_communication_id = \'NPWR00345_00\' AND te.order_id = 42 AND te.earned_date >= \'2012-11-16\'',
                'te.np_communication_id = \'NPWR00345_00\' AND te.order_id = 43 AND te.earned_date >= \'2012-11-16\'',
                'te.np_communication_id = \'NPWR00345_00\' AND te.order_id = 44 AND te.earned_date >= \'2012-11-16\'',
                'te.np_communication_id = \'NPWR00345_00\' AND te.order_id = 45 AND te.earned_date >= \'2012-11-16\'',
                'te.np_communication_id = \'NPWR00345_00\' AND te.order_id = 46 AND te.earned_date >= \'2012-11-16\'',
                'te.np_communication_id = \'NPWR00345_00\' AND te.order_id = 47 AND te.earned_date >= \'2012-11-16\'',
                'te.np_communication_id = \'NPWR00345_00\' AND te.order_id = 48 AND te.earned_date >= \'2012-11-16\'',
                'te.np_communication_id = \'NPWR00345_00\' AND te.order_id = 49 AND te.earned_date >= \'2012-11-16\'',
                'te.np_communication_id = \'NPWR00345_00\' AND te.order_id = 50 AND te.earned_date >= \'2012-11-16\'',
            ],
        ],
        [
            'label' => 'Breach & Clear',
            'conditions' => [
                'te.np_communication_id = \'NPWR08030_00\' AND te.order_id = 6',
            ],
        ],
        [
            'label' => 'NINJA GAIDEN Σ2 PLUS',
            'conditions' => [
                'te.np_communication_id = \'NPWR04361_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR04361_00\' AND te.order_id = 39',
                'te.np_communication_id = \'NPWR04361_00\' AND te.order_id = 40',
            ],
        ],
        [
            'label' => 'Night Trap - 25th Anniversary Edition',
            'conditions' => [
                'te.np_communication_id = \'NPWR14011_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR14011_00\' AND te.order_id = 12',
            ],
        ],
        [
            'label' => 'Tachyon Project',
            'conditions' => [
                'te.np_communication_id = \'NPWR10143_00\' AND te.order_id = 11',
                'te.np_communication_id = \'NPWR10143_00\' AND te.order_id = 12',
                'te.np_communication_id = \'NPWR10143_00\' AND te.order_id = 13',
            ],
        ],
        [
            'label' => 'Mr. Pumpkin Adventure',
            'conditions' => [
                'te.np_communication_id = \'NPWR12133_00\' AND te.order_id = 8',
                'te.np_communication_id = \'NPWR12133_00\' AND te.order_id = 10',
                'te.np_communication_id = \'NPWR12133_00\' AND te.order_id = 12',
            ],
        ],
        [
            'label' => 'SBKX Superbike World Championship',
            'conditions' => [
                'te.np_communication_id = \'NPWR00934_00\' AND te.order_id = 33',
            ],
        ],
        [
            'label' => 'Blues and Bullets',
            'conditions' => [
                'te.np_communication_id = \'NPWR09796_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR09796_00\' AND te.order_id = 15',
                'te.np_communication_id = \'NPWR09796_00\' AND te.order_id = 16',
                'te.np_communication_id = \'NPWR09796_00\' AND te.order_id = 17',
                'te.np_communication_id = \'NPWR09796_00\' AND te.order_id = 18',
                'te.np_communication_id = \'NPWR09796_00\' AND te.order_id = 19',
                'te.np_communication_id = \'NPWR09796_00\' AND te.order_id = 20',
                'te.np_communication_id = \'NPWR09796_00\' AND te.order_id = 21',
                'te.np_communication_id = \'NPWR09796_00\' AND te.order_id = 22',
                'te.np_communication_id = \'NPWR09796_00\' AND te.order_id = 23',
                'te.np_communication_id = \'NPWR09796_00\' AND te.order_id = 24',
                'te.np_communication_id = \'NPWR09796_00\' AND te.order_id = 25',
                'te.np_communication_id = \'NPWR09796_00\' AND te.order_id = 26',
                'te.np_communication_id = \'NPWR09796_00\' AND te.order_id = 27',
                'te.np_communication_id = \'NPWR09796_00\' AND te.order_id = 28',
                'te.np_communication_id = \'NPWR09796_00\' AND te.order_id = 29',
                'te.np_communication_id = \'NPWR09796_00\' AND te.order_id = 30',
                'te.np_communication_id = \'NPWR09796_00\' AND te.order_id = 31',
                'te.np_communication_id = \'NPWR09796_00\' AND te.order_id = 32',
                'te.np_communication_id = \'NPWR09796_00\' AND te.order_id = 33',
                'te.np_communication_id = \'NPWR09796_00\' AND te.order_id = 34',
                'te.np_communication_id = \'NPWR09796_00\' AND te.order_id = 35',
            ],
        ],
        [
            'label' => 'Boundless (EU)',
            'conditions' => [
                'te.np_communication_id = \'NPWR16180_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR16180_00\' AND te.order_id = 5',
                'te.np_communication_id = \'NPWR16180_00\' AND te.order_id = 6',
                'te.np_communication_id = \'NPWR16180_00\' AND te.order_id = 54',
            ],
        ],
        [
            'label' => 'Boundless (NA)',
            'conditions' => [
                'te.np_communication_id = \'NPWR16181_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR16181_00\' AND te.order_id = 5',
                'te.np_communication_id = \'NPWR16181_00\' AND te.order_id = 6',
                'te.np_communication_id = \'NPWR16181_00\' AND te.order_id = 54',
            ],
        ],
        [
            'label' => 'Conarium',
            'conditions' => [
                'te.np_communication_id = \'NPWR16018_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR16018_00\' AND te.order_id = 11',
                'te.np_communication_id = \'NPWR16018_00\' AND te.order_id = 15',
                'te.np_communication_id = \'NPWR16018_00\' AND te.order_id = 16',
                'te.np_communication_id = \'NPWR16018_00\' AND te.order_id = 23',
            ],
        ],
        [
            'label' => 'Borderlands: Game of the Year Edition',
            'conditions' => [
                'te.np_communication_id = \'NPWR01486_00\' AND te.order_id = 71',
                'te.np_communication_id = \'NPWR01486_00\' AND te.order_id = 72',
                'te.np_communication_id = \'NPWR01486_00\' AND te.order_id = 73',
                'te.np_communication_id = \'NPWR01486_00\' AND te.order_id = 74',
                'te.np_communication_id = \'NPWR01486_00\' AND te.order_id = 75',
                'te.np_communication_id = \'NPWR01486_00\' AND te.order_id = 76',
                'te.np_communication_id = \'NPWR01486_00\' AND te.order_id = 77',
                'te.np_communication_id = \'NPWR01486_00\' AND te.order_id = 78',
                'te.np_communication_id = \'NPWR01486_00\' AND te.order_id = 79',
                'te.np_communication_id = \'NPWR01486_00\' AND te.order_id = 80',
            ],
        ],
        [
            'label' => 'Defiance 2050',
            'conditions' => [
                'te.np_communication_id = \'NPWR14294_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR14294_00\' AND te.order_id = 10',
                'te.np_communication_id = \'NPWR14294_00\' AND te.order_id = 11',
            ],
        ],
        [
            'label' => 'ドラゴンズドグマ オンライン (Dragon\'s Dogma Online)',
            'conditions' => [
                'te.np_communication_id = \'NPWR04029_00\' AND te.order_id = 0 AND te.earned_date >= \'2019-12-06\'',
                'te.np_communication_id = \'NPWR04029_00\' AND te.order_id = 1 AND te.earned_date >= \'2019-12-06\'',
                'te.np_communication_id = \'NPWR04029_00\' AND te.order_id = 2 AND te.earned_date >= \'2019-12-06\'',
                'te.np_communication_id = \'NPWR04029_00\' AND te.order_id = 3 AND te.earned_date >= \'2019-12-06\'',
                'te.np_communication_id = \'NPWR04029_00\' AND te.order_id = 4 AND te.earned_date >= \'2019-12-06\'',
                'te.np_communication_id = \'NPWR04029_00\' AND te.order_id = 5 AND te.earned_date >= \'2019-12-06\'',
                'te.np_communication_id = \'NPWR04029_00\' AND te.order_id = 6 AND te.earned_date >= \'2019-12-06\'',
                'te.np_communication_id = \'NPWR04029_00\' AND te.order_id = 7 AND te.earned_date >= \'2019-12-06\'',
                'te.np_communication_id = \'NPWR04029_00\' AND te.order_id = 8 AND te.earned_date >= \'2019-12-06\'',
                'te.np_communication_id = \'NPWR04029_00\' AND te.order_id = 9 AND te.earned_date >= \'2019-12-06\'',
                'te.np_communication_id = \'NPWR04029_00\' AND te.order_id = 10 AND te.earned_date >= \'2019-12-06\'',
                'te.np_communication_id = \'NPWR04029_00\' AND te.order_id = 11 AND te.earned_date >= \'2019-12-06\'',
                'te.np_communication_id = \'NPWR04029_00\' AND te.order_id = 12 AND te.earned_date >= \'2019-12-06\'',
            ],
        ],
        [
            'label' => 'Drunkn Bar Fight',
            'conditions' => [
                'te.np_communication_id = \'NPWR14225_00\' AND te.order_id = 6 AND te.earned_date <= \'2020-04-12\'',
                'te.np_communication_id = \'NPWR14225_00\' AND te.order_id = 9 AND te.earned_date <= \'2020-04-12\'',
            ],
        ],
        [
            'label' => 'Dungeon Rushers',
            'conditions' => [
                'te.np_communication_id = \'NPWR12850_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR12850_00\' AND te.order_id = 20',
            ],
        ],
        [
            'label' => 'Dungeon Rushers',
            'conditions' => [
                'te.np_communication_id = \'NPWR12851_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR12851_00\' AND te.order_id = 20',
            ],
        ],
        [
            'label' => 'Epic World',
            'conditions' => [
                'te.np_communication_id = \'NPWR13748_00\' AND te.order_id = 4',
                'te.np_communication_id = \'NPWR13748_00\' AND te.order_id = 6',
                'te.np_communication_id = \'NPWR13748_00\' AND te.order_id = 9',
            ],
        ],
        [
            'label' => 'Epic World',
            'conditions' => [
                'te.np_communication_id = \'NPWR13749_00\' AND te.order_id = 4',
                'te.np_communication_id = \'NPWR13749_00\' AND te.order_id = 6',
                'te.np_communication_id = \'NPWR13749_00\' AND te.order_id = 9',
            ],
        ],
        [
            'label' => 'Forestry 2017 - The Simulation',
            'conditions' => [
                'te.np_communication_id = \'NPWR10743_00\' AND te.order_id = 9',
            ],
        ],
        [
            'label' => 'Forestry 2017 - The Simulation',
            'conditions' => [
                'te.np_communication_id = \'NPWR11373_00\' AND te.order_id = 9',
            ],
        ],
        [
            'label' => 'Kerbal Space Program',
            'conditions' => [
                'te.np_communication_id = \'NPWR10806_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR10806_00\' AND te.order_id = 23',
            ],
        ],
        [
            'label' => 'Lock\'s Quest',
            'conditions' => [
                'te.np_communication_id = \'NPWR12464_00\' AND te.order_id = 4',
                'te.np_communication_id = \'NPWR12464_00\' AND te.order_id = 11',
            ],
        ],
        [
            'label' => 'NBA 2K17',
            'conditions' => [
                'te.np_communication_id = \'NPWR11010_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR11010_00\' AND te.order_id = 22',
            ],
        ],
        [
            'label' => 'NBA 2K20',
            'conditions' => [
                'te.np_communication_id = \'NPWR18341_00\' AND te.order_id = 0 AND te.earned_date < \'2020-03-01\'',
                'te.np_communication_id = \'NPWR18341_00\' AND te.order_id = 35 AND te.earned_date < \'2020-03-01\'',
            ],
        ],
        [
            'label' => 'NBA LIVE 14',
            'conditions' => [
                'te.np_communication_id = \'NPWR05357_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR05357_00\' AND te.order_id = 10',
            ],
        ],
        [
            'label' => 'One Way Trip',
            'conditions' => [
                'te.np_communication_id = \'NPWR11687_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR11687_00\' AND te.order_id = 6',
            ],
        ],
        [
            'label' => 'Panda Hero',
            'conditions' => [
                'te.np_communication_id = \'NPWR16665_00\' AND te.order_id = 10',
                'te.np_communication_id = \'NPWR16665_00\' AND te.order_id = 11',
                'te.np_communication_id = \'NPWR16665_00\' AND te.order_id = 12',
            ],
        ],
        [
            'label' => 'Panda Hero',
            'conditions' => [
                'te.np_communication_id = \'NPWR17127_00\' AND te.order_id = 10',
                'te.np_communication_id = \'NPWR17127_00\' AND te.order_id = 11',
                'te.np_communication_id = \'NPWR17127_00\' AND te.order_id = 12',
            ],
        ],
        [
            'label' => 'Professional Farmer 2017',
            'conditions' => [
                'te.np_communication_id = \'NPWR10742_00\' AND te.order_id = 8',
            ],
        ],
        [
            'label' => 'Rugby 18',
            'conditions' => [
                'te.np_communication_id = \'NPWR13717_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR13717_00\' AND te.order_id = 2',
            ],
        ],
        [
            'label' => 'Shiny',
            'conditions' => [
                'te.np_communication_id = \'NPWR13751_00\' AND te.order_id = 0 AND te.earned_date < \'2020-09-24\'',
                'te.np_communication_id = \'NPWR13751_00\' AND te.order_id = 1 AND te.earned_date < \'2020-09-24\'',
                'te.np_communication_id = \'NPWR13751_00\' AND te.order_id = 2 AND te.earned_date < \'2020-09-24\'',
                'te.np_communication_id = \'NPWR13751_00\' AND te.order_id = 9 AND te.earned_date < \'2020-09-24\'',
                'te.np_communication_id = \'NPWR13751_00\' AND te.order_id = 12 AND te.earned_date < \'2020-09-24\'',
                'te.np_communication_id = \'NPWR13751_00\' AND te.order_id = 39 AND te.earned_date < \'2020-09-24\'',
                'te.np_communication_id = \'NPWR13751_00\' AND te.order_id = 40 AND te.earned_date < \'2020-09-24\'',
                'te.np_communication_id = \'NPWR13751_00\' AND te.order_id = 41 AND te.earned_date < \'2020-09-24\'',
                'te.np_communication_id = \'NPWR13751_00\' AND te.order_id = 42 AND te.earned_date < \'2020-09-24\'',
                'te.np_communication_id = \'NPWR13751_00\' AND te.order_id = 48 AND te.earned_date < \'2020-09-24\'',
            ],
        ],
        [
            'label' => 'Solitaire',
            'conditions' => [
                'te.np_communication_id = \'NPWR10988_00\' AND te.order_id = 2',
            ],
        ],
        [
            'label' => 'Tango Fiesta',
            'conditions' => [
                'te.np_communication_id = \'NPWR11297_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR11297_00\' AND te.order_id = 5',
                'te.np_communication_id = \'NPWR11297_00\' AND te.order_id = 7',
                'te.np_communication_id = \'NPWR11297_00\' AND te.order_id = 16',
            ],
        ],
        [
            'label' => 'Tethered',
            'conditions' => [
                'te.np_communication_id = \'NPWR11977_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR11977_00\' AND te.order_id = 18',
            ],
        ],
        [
            'label' => 'Tour de France 2019',
            'conditions' => [
                'te.np_communication_id = \'NPWR17220_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR17220_00\' AND te.order_id = 41',
            ],
        ],
        [
            'label' => 'Toy Soldiers War Chest',
            'conditions' => [
                'te.np_communication_id = \'NPWR06434_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR06434_00\' AND te.order_id = 10',
            ],
        ],
        [
            'label' => 'Trickster VR',
            'conditions' => [
                'te.np_communication_id = \'NPWR15045_00\' AND te.order_id = 11',
            ],
        ],
        [
            'label' => 'Wander (EU)',
            'conditions' => [
                'te.np_communication_id = \'NPWR08948_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR08948_00\' AND te.order_id = 18',
                'te.np_communication_id = \'NPWR08948_00\' AND te.order_id = 30',
            ],
        ],
        [
            'label' => 'Wander (NA)',
            'conditions' => [
                'te.np_communication_id = \'NPWR08982_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR08982_00\' AND te.order_id = 18',
                'te.np_communication_id = \'NPWR08982_00\' AND te.order_id = 30',
            ],
        ],
        [
            'label' => 'Five Nights at Freddy\'s 2',
            'conditions' => [
                'te.np_communication_id = \'NPWR19583_00\' AND te.order_id = 7 AND te.earned_date < \'2022-04-01\'',
            ],
        ],
        [
            'label' => 'Infinity Runner',
            'conditions' => [
                'te.np_communication_id = \'NPWR09492_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR09492_00\' AND te.order_id = 55',
            ],
        ],
        [
            'label' => 'Season Match Bundle - Part 1 and 2',
            'conditions' => [
                'te.np_communication_id = \'NPWR17124_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR17124_00\' AND te.order_id = 7',
                'te.np_communication_id = \'NPWR17124_00\' AND te.order_id = 8',
                'te.np_communication_id = \'NPWR17124_00\' AND te.order_id = 9',
            ],
        ],
        [
            'label' => 'Shaq Fu: A Legend Reborn',
            'conditions' => [
                'te.np_communication_id = \'NPWR12063_00\' AND te.order_id = 21',
                'te.np_communication_id = \'NPWR12063_00\' AND te.order_id = 22',
                'te.np_communication_id = \'NPWR12063_00\' AND te.order_id = 23',
                'te.np_communication_id = \'NPWR12063_00\' AND te.order_id = 24',
                'te.np_communication_id = \'NPWR12063_00\' AND te.order_id = 25',
                'te.np_communication_id = \'NPWR12063_00\' AND te.order_id = 26',
                'te.np_communication_id = \'NPWR12063_00\' AND te.order_id = 27',
                'te.np_communication_id = \'NPWR12063_00\' AND te.order_id = 28',
                'te.np_communication_id = \'NPWR12063_00\' AND te.order_id = 29',
                'te.np_communication_id = \'NPWR12063_00\' AND te.order_id = 30',
                'te.np_communication_id = \'NPWR12063_00\' AND te.order_id = 31',
                'te.np_communication_id = \'NPWR12063_00\' AND te.order_id = 32',
                'te.np_communication_id = \'NPWR12063_00\' AND te.order_id = 33',
            ],
        ],
        [
            'label' => 'Super Mutant Alien Assault',
            'conditions' => [
                'te.np_communication_id = \'NPWR11013_00\' AND te.order_id = 16',
            ],
        ],
        [
            'label' => 'Felix The Reaper (EU)',
            'conditions' => [
                'te.np_communication_id = \'NPWR18992_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR18992_00\' AND te.order_id = 23',
            ],
        ],
        [
            'label' => 'Titan Quest',
            'conditions' => [
                'te.np_communication_id = \'NPWR13165_00\' AND te.order_id = 31',
                'te.np_communication_id = \'NPWR13165_00\' AND te.order_id = 39',
            ],
        ],
        [
            'label' => 'Element Space',
            'conditions' => [
                'te.np_communication_id = \'MERGE_011562\' AND te.order_id = 0',
                'te.np_communication_id = \'MERGE_011562\' AND te.order_id = 17',
                'te.np_communication_id = \'MERGE_011562\' AND te.order_id = 18',
                'te.np_communication_id = \'MERGE_011562\' AND te.order_id = 25',
                'te.np_communication_id = \'MERGE_011562\' AND te.order_id = 56',
            ],
        ],
        [
            'label' => 'Hunting Simulator 2',
            'conditions' => [
                'te.np_communication_id = \'NPWR19903_00\' AND te.order_id = 0 AND te.earned_date < \'2020-09-01\'',
                'te.np_communication_id = \'NPWR19903_00\' AND te.order_id = 27 AND te.earned_date < \'2020-09-01\'',
            ],
        ],
        [
            'label' => 'Marvel\'s Avengers',
            'conditions' => [
                'te.np_communication_id = \'NPWR16769_00\' AND te.order_id = 0 AND te.earned_date < \'2020-09-19\'',
                'te.np_communication_id = \'NPWR16769_00\' AND te.order_id = 7 AND te.earned_date < \'2020-09-19\'',
            ],
        ],
        [
            'label' => 'Tokyo Tattoo Girls',
            'conditions' => [
                'te.np_communication_id = \'NPWR14063_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR14063_00\' AND te.order_id = 35',
            ],
        ],
        [
            'label' => 'Indivisible',
            'conditions' => [
                'te.np_communication_id = \'NPWR13128_00\' AND te.order_id = 34',
                'te.np_communication_id = \'NPWR13128_00\' AND te.order_id = 35',
                'te.np_communication_id = \'NPWR13128_00\' AND te.order_id = 36',
            ],
        ],
        [
            'label' => 'Indivisible [JP]',
            'conditions' => [
                'te.np_communication_id = \'NPWR19862_00\' AND te.order_id = 35',
            ],
        ],
        [
            'label' => 'Alien Spidy',
            'conditions' => [
                'te.np_communication_id = \'NPWR03634_00\' AND te.order_id = 6',
                'te.np_communication_id = \'NPWR03634_00\' AND te.order_id = 11',
            ],
        ],
        [
            'label' => 'Bodycheck',
            'conditions' => [
                'te.np_communication_id = \'NPWR06410_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR06410_00\' AND te.order_id = 4',
                'te.np_communication_id = \'NPWR06410_00\' AND te.order_id = 5',
                'te.np_communication_id = \'NPWR06410_00\' AND te.order_id = 9',
                'te.np_communication_id = \'NPWR06410_00\' AND te.order_id = 12',
                'te.np_communication_id = \'NPWR06410_00\' AND te.order_id = 15',
                'te.np_communication_id = \'NPWR06410_00\' AND te.order_id = 29',
                'te.np_communication_id = \'NPWR06410_00\' AND te.order_id = 35',
            ],
        ],
        [
            'label' => 'The Binding of Isaac: Rebirth (PS4/Vita - Japan)',
            'conditions' => [
                'te.np_communication_id = \'NPWR09566_00\' AND te.order_id = 0',
                'te.np_communication_id = \'NPWR09566_00\' AND te.order_id = 56',
                'te.np_communication_id = \'NPWR09566_00\' AND te.order_id = 58',
            ],
        ],
        [
            'label' => 'Need for Speed: The Run',
            'conditions' => [
                'te.np_communication_id = \'NPWR01835_00\' AND te.order_id = 39 AND te.earned_date >= \'2021-09-01\'',
                'te.np_communication_id = \'NPWR01835_00\' AND te.order_id = 40 AND te.earned_date >= \'2021-09-01\'',
                'te.np_communication_id = \'NPWR01835_00\' AND te.order_id = 41 AND te.earned_date >= \'2021-09-01\'',
                'te.np_communication_id = \'NPWR01835_00\' AND te.order_id = 42 AND te.earned_date >= \'2021-09-01\'',
            ],
        ],
    ];
    private const SECTION_DEFINITIONS = [
        [
            'title' => 'FUEL',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, fuel_start.earned_date, fuel_end.earned_date)) AS time_difference
                FROM
                    player p
                JOIN trophy_earned fuel_start ON
                    fuel_start.account_id = p.account_id
                    AND fuel_start.np_communication_id = 'NPWR00481_00'
                    AND fuel_start.order_id = 33
                JOIN trophy_earned fuel_end ON
                    fuel_end.account_id = p.account_id
                    AND fuel_end.np_communication_id = 'NPWR00481_00'
                    AND fuel_end.order_id = 34
                WHERE
                    p.status != 1
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/4390-fuel/%s?sort=date',
        ],
        [
            'title' => 'SOCOM: U.S. NAVY SEALS CONFRONTATION',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, socom_start.earned_date, socom_end.earned_date)) AS time_difference
                FROM
                    player p
                JOIN trophy_earned socom_start ON
                    socom_start.account_id = p.account_id
                    AND socom_start.np_communication_id = 'NPWR00302_00'
                    AND socom_start.order_id = 32
                JOIN trophy_earned socom_end ON
                    socom_end.account_id = p.account_id
                    AND socom_end.np_communication_id = 'NPWR00302_00'
                    AND socom_end.order_id = 33
                WHERE
                    p.status != 1
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/4233-socom-us-navy-seals-confrontation/%s?sort=date',
        ],
        [
            'title' => 'Resonance of Fate (Lap Two Complete < A New Beginning)',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date) AS time_difference
                FROM
                    player p
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = p.account_id
                    AND trophy_start.np_communication_id = 'NPWR01103_00'
                    AND trophy_start.order_id = 38
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = p.account_id
                    AND trophy_end.np_communication_id = 'NPWR01103_00'
                    AND trophy_end.order_id = 48
                WHERE
                    p.status != 1
                HAVING
                    time_difference <= 0
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/2704-resonance-of-fate/%s?sort=date',
        ],
        [
            'title' => 'End of Eternity (2周目クリア < 2周目突入)',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date) AS time_difference
                FROM
                    player p
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = p.account_id
                    AND trophy_start.np_communication_id = 'NPWR00987_00'
                    AND trophy_start.order_id = 38
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = p.account_id
                    AND trophy_end.np_communication_id = 'NPWR00987_00'
                    AND trophy_end.order_id = 48
                WHERE
                    p.status != 1
                HAVING
                    time_difference <= 0
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/5703-end-of-eternity/%s?sort=date',
        ],
        [
            'title' => 'Catherine: Full Body',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date) AS time_difference
                FROM
                    player p
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = p.account_id
                    AND trophy_start.np_communication_id = 'NPWR17582_00'
                    AND trophy_start.order_id = 50
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = p.account_id
                    AND trophy_end.np_communication_id = 'NPWR17582_00'
                    AND trophy_end.order_id = 51
                WHERE
                    p.status != 1
                HAVING
                    time_difference <= 0
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/4556-catherine-full-body/%s',
        ],
        [
            'title' => '凱薩琳FULL BODY',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date) AS time_difference
                FROM
                    player p
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = p.account_id
                    AND trophy_start.np_communication_id = 'NPWR17415_00'
                    AND trophy_start.order_id = 50
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = p.account_id
                    AND trophy_end.np_communication_id = 'NPWR17415_00'
                    AND trophy_end.order_id = 51
                WHERE
                    p.status != 1
                HAVING
                    time_difference <= 0
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/7556-kai-sa-linfull-body/%s',
        ],
        [
            'title' => 'キャサリン・フルボディ',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date) AS time_difference
                FROM
                    player p
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = p.account_id
                    AND trophy_start.np_communication_id = 'NPWR14836_00'
                    AND trophy_start.order_id = 50
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = p.account_id
                    AND trophy_end.np_communication_id = 'NPWR14836_00'
                    AND trophy_end.order_id = 51
                WHERE
                    p.status != 1
                HAVING
                    time_difference <= 0
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/6489-kyasarinfurubodi/%s',
        ],
        [
            'title' => 'Lost Planet 2 (200-Chapter Playback <-> 300-Chapter Playback)',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date) AS time_difference
                FROM
                    player p
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = p.account_id
                    AND trophy_start.np_communication_id = 'NPWR00928_00'
                    AND trophy_start.order_id = 10
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = p.account_id
                    AND trophy_end.np_communication_id = 'NPWR00928_00'
                    AND trophy_end.order_id = 11
                WHERE
                    p.status != 1
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/4237-lost-planet-2/%s?sort=date',
        ],
        [
            'title' => 'Lost Planet 2 (Snow Pirate Leader <-> Snow Pirate Commander)',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date) AS time_difference
                FROM
                    player p
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = p.account_id
                    AND trophy_start.np_communication_id = 'NPWR00928_00'
                    AND trophy_start.order_id = 19
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = p.account_id
                    AND trophy_end.np_communication_id = 'NPWR00928_00'
                    AND trophy_end.order_id = 20
                WHERE
                    p.status != 1
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/4237-lost-planet-2/%s?sort=date',
        ],
        [
            'title' => 'Resident Evil: Revelations [PS4] (Bonus Legend <-> Bonus Demi-god)',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, rer_start.earned_date, rer_end.earned_date)) AS time_difference
                FROM
                    player p
                JOIN trophy_earned rer_start ON
                    rer_start.account_id = p.account_id
                    AND rer_start.np_communication_id = 'NPWR11777_00'
                    AND rer_start.order_id = 54
                JOIN trophy_earned rer_end ON
                    rer_end.account_id = p.account_id
                    AND rer_end.np_communication_id = 'NPWR11777_00'
                    AND rer_end.order_id = 55
                WHERE
                    p.status != 1
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/4663-resident-evil-revelations/%s?sort=date',
        ],
        [
            'title' => 'Resident Evil: Revelations [PS4] (Meteoric Rise <-> Top of My Game)',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, rer_start.earned_date, rer_end.earned_date)) AS time_difference
                FROM
                    player p
                JOIN trophy_earned rer_start ON
                    rer_start.account_id = p.account_id
                    AND rer_start.np_communication_id = 'NPWR11777_00'
                    AND rer_start.order_id = 37
                JOIN trophy_earned rer_end ON
                    rer_end.account_id = p.account_id
                    AND rer_end.np_communication_id = 'NPWR11777_00'
                    AND rer_end.order_id = 38
                WHERE
                    p.status != 1
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/4663-resident-evil-revelations/%s?sort=date',
        ],
        [
            'title' => 'Resident Evil: Revelations [PS3] (Bonus Legend <-> Bonus Demi-god)',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, rer_start.earned_date, rer_end.earned_date)) AS time_difference
                FROM
                    player p
                JOIN trophy_earned rer_start ON
                    rer_start.account_id = p.account_id
                    AND rer_start.np_communication_id = 'NPWR03903_00'
                    AND rer_start.order_id = 49
                JOIN trophy_earned rer_end ON
                    rer_end.account_id = p.account_id
                    AND rer_end.np_communication_id = 'NPWR03903_00'
                    AND rer_end.order_id = 50
                WHERE
                    p.status != 1
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/3804-resident-evil-revelations/%s?sort=date',
        ],
        [
            'title' => 'Resident Evil: Revelations [PS3] (Meteoric Rise <-> Top of My Game)',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, rer_start.earned_date, rer_end.earned_date)) AS time_difference
                FROM
                    player p
                JOIN trophy_earned rer_start ON
                    rer_start.account_id = p.account_id
                    AND rer_start.np_communication_id = 'NPWR03903_00'
                    AND rer_start.order_id = 36
                JOIN trophy_earned rer_end ON
                    rer_end.account_id = p.account_id
                    AND rer_end.np_communication_id = 'NPWR03903_00'
                    AND rer_end.order_id = 37
                WHERE
                    p.status != 1
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/3804-resident-evil-revelations/%s?sort=date',
        ],
        [
            'title' => 'Angry Birds Trilogy [PS3] (Block Breaker <-> Block Annihilator)',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, abt_start.earned_date, abt_end.earned_date)) AS time_difference
                FROM
                    player p
                JOIN trophy_earned abt_start ON
                    abt_start.account_id = p.account_id
                    AND abt_start.np_communication_id = 'NPWR03771_00'
                    AND abt_start.order_id = 30
                JOIN trophy_earned abt_end ON
                    abt_end.account_id = p.account_id
                    AND abt_end.np_communication_id = 'NPWR03771_00'
                    AND abt_end.order_id = 31
                WHERE
                    p.status != 1
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/3810-angry-birds-trilogy/%s?sort=date',
        ],
        [
            'title' => 'Terminator Salvation',
            'query' => <<<'SQL'
                SELECT
                    account_id,
                    online_id,
                    trophy_count
                FROM
                    player p
                JOIN(
                    SELECT account_id,
                        COUNT(account_id) AS trophy_count
                    FROM
                        trophy_earned te
                    WHERE
                        np_communication_id = 'NPWR00623_00' AND order_id != 9 AND earned_date >=(
                        SELECT
                            earned_date
                        FROM
                            trophy_earned
                        WHERE
                            account_id = te.account_id AND np_communication_id = 'NPWR00623_00' AND order_id = 9
                    )
                GROUP BY
                    account_id
                ) trophy_counter USING(account_id)
                WHERE
                    p.status != 1
                HAVING
                    trophy_count >= 9
                ORDER BY
                    online_id
            SQL,
            'linkPattern' => '/game/294-terminator-salvation/%s?sort=date',
        ],
        [
            'title' => 'F1 Race Stars',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date)) AS time_difference
                FROM
                    player p
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = p.account_id
                    AND trophy_start.np_communication_id = 'NPWR03734_00'
                    AND trophy_start.order_id = 3
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = p.account_id
                    AND trophy_end.np_communication_id = 'NPWR03734_00'
                    AND trophy_end.order_id = 4
                WHERE
                    p.status != 1
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/4866-f1-race-stars/%s?sort=date',
        ],
        [
            'title' => 'Mega Man: Legacy Collection',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date)) AS time_difference
                FROM
                    player p
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = p.account_id
                    AND trophy_start.np_communication_id = 'NPWR09098_00'
                    AND trophy_start.order_id = 6
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = p.account_id
                    AND trophy_end.np_communication_id = 'NPWR09098_00'
                    AND trophy_end.order_id = 7
                WHERE
                    p.status != 1
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/179-mega-man-legacy-collection/%s?sort=date',
        ],
        [
            'title' => 'Batman: Arkham Asylum',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date)) AS time_difference
                FROM
                    player p
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = p.account_id
                    AND trophy_start.np_communication_id = 'NPWR00626_00'
                    AND trophy_start.order_id = 31
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = p.account_id
                    AND trophy_end.np_communication_id = 'NPWR00626_00'
                    AND trophy_end.order_id = 32
                WHERE
                    p.status != 1
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/333-batman-arkham-asylum/%s?sort=date',
        ],
        [
            'title' => 'Batman: Arkham Asylum (JP)',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date)) AS time_difference
                FROM
                    player p
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = p.account_id
                    AND trophy_start.np_communication_id = 'NPWR01012_00'
                    AND trophy_start.order_id = 31
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = p.account_id
                    AND trophy_end.np_communication_id = 'NPWR01012_00'
                    AND trophy_end.order_id = 32
                WHERE
                    p.status != 1
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/3131-batman-arkham-asylum/%s?sort=date',
        ],
        [
            'title' => 'Dead Space',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date)) AS time_difference
                FROM
                    player p
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = p.account_id
                    AND trophy_start.np_communication_id = 'NPWR00464_00'
                    AND trophy_start.order_id = 19
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = p.account_id
                    AND trophy_end.np_communication_id = 'NPWR00464_00'
                    AND trophy_end.order_id = 20
                WHERE
                    p.status != 1
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/3200-dead-space/%s?sort=date',
        ],
        [
            'title' => 'Street Fighter X Tekken [PSVITA] (Transcend All You Know <-> Your Legend Will Never Die)',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date)) AS time_difference
                FROM
                    player p
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = p.account_id
                    AND trophy_start.np_communication_id = 'NPWR03139_00'
                    AND trophy_start.order_id = 36
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = p.account_id
                    AND trophy_end.np_communication_id = 'NPWR03139_00'
                    AND trophy_end.order_id = 37
                WHERE
                    p.status != 1
                HAVING
                    time_difference <= 600
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/3474-street-fighter-x-tekken/%s?sort=date',
        ],
        [
            'title' => 'Street Fighter X Tekken [PS3] (Transcend All You Know <-> Your Legend Will Never Die)',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date)) AS time_difference
                FROM
                    player p
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = p.account_id
                    AND trophy_start.np_communication_id = 'NPWR01781_00'
                    AND trophy_start.order_id = 38
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = p.account_id
                    AND trophy_end.np_communication_id = 'NPWR01781_00'
                    AND trophy_end.order_id = 39
                WHERE
                    p.status != 1
                HAVING
                    time_difference <= 600
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/4253-street-fighter-x-tekken/%s?sort=date',
        ],
        [
            'title' => 'Fat Princess',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date)) AS time_difference
                FROM
                    player p
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = p.account_id
                    AND trophy_start.np_communication_id = 'NPWR00737_00'
                    AND trophy_start.order_id = 0
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = p.account_id
                    AND trophy_end.np_communication_id = 'NPWR00737_00'
                    AND trophy_end.order_id = 26
                WHERE
                    p.status != 1
                HAVING
                    time_difference <= 300
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/279-fat-princess/%s?sort=date',
        ],
        [
            'title' => 'Code Vein (Determiner of Fate <-> Heirs)',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date) AS time_difference
                FROM
                    player p
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = p.account_id
                    AND trophy_start.np_communication_id = 'NPWR14318_00'
                    AND trophy_start.order_id = 2
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = p.account_id
                    AND trophy_end.np_communication_id = 'NPWR14318_00'
                    AND trophy_end.order_id = 39
                WHERE
                    p.status != 1
                HAVING
                    time_difference >= 10
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/3243-code-vein/%s?sort=date',
        ],
        [
            'title' => 'Code Vein (Determiner of Fate <-> To Eternity)',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date) AS time_difference
                FROM
                    player p
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = p.account_id
                    AND trophy_start.np_communication_id = 'NPWR14318_00'
                    AND trophy_start.order_id = 2
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = p.account_id
                    AND trophy_end.np_communication_id = 'NPWR14318_00'
                    AND trophy_end.order_id = 40
                WHERE
                    p.status != 1
                HAVING
                    time_difference >= 10
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/3243-code-vein/%s?sort=date',
        ],
        [
            'title' => 'Code Vein (Determiner of Fate <-> Dweller in the Dark)',
            'query' => <<<'SQL'
                SELECT
                    p.account_id,
                    p.online_id,
                    TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date) AS time_difference
                FROM
                    player p
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = p.account_id
                    AND trophy_start.np_communication_id = 'NPWR14318_00'
                    AND trophy_start.order_id = 2
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = p.account_id
                    AND trophy_end.np_communication_id = 'NPWR14318_00'
                    AND trophy_end.order_id = 41
                WHERE
                    p.status != 1
                HAVING
                    time_difference >= 10
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/3243-code-vein/%s?sort=date',
        ],
    ];

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function createReport(): PossibleCheaterReport
    {
        return new PossibleCheaterReport(
            $this->buildGeneralReportEntries(),
            $this->buildSectionReports()
        );
    }

    private function buildGeneralWhereClause(): string
    {
        $conditions = [];

        foreach ($this->getGeneralRuleGroups() as $group) {
            foreach ($group->getRules() as $rule) {
                $conditions[] = '(' . $rule->getCondition() . ')';
            }
        }

        if ($conditions === []) {
            return '()';
        }

        return '(' . implode(' OR ', $conditions) . ')';
    }

    /**
     * @return PossibleCheaterReportEntry[]
     */
    private function buildGeneralReportEntries(): array
    {
        return array_map(
            static fn(array $row): PossibleCheaterReportEntry => PossibleCheaterReportEntry::fromArray($row),
            $this->fetchGeneralPossibleCheaterRows()
        );
    }

    /**
     * @return PossibleCheaterReportSection[]
     */
    private function buildSectionReports(): array
    {
        $sections = [];

        foreach ($this->getSectionDefinitions() as $definition) {
            $entries = array_map(
                static function (array $row) use ($definition): PossibleCheaterReportSectionEntry {
                    $onlineId = (string) $row['online_id'];

                    return new PossibleCheaterReportSectionEntry(
                        $definition->buildLink($onlineId),
                        $onlineId,
                        (int) $row['account_id']
                    );
                },
                $this->fetchAll($definition->getQuery())
            );

            $sections[] = new PossibleCheaterReportSection(
                $definition->getTitle(),
                $entries
            );
        }

        return $sections;
    }

    /**
     * @return list<array{account_id:int, player_name:string, game_id:int, game_name:string}>
     */
    private function fetchGeneralPossibleCheaterRows(): array
    {
        $whereClause = $this->buildGeneralWhereClause();

        $sql = <<<'SQL'
        SELECT
            first_games.account_id,
            first_games.player_name,
            tt_first.id AS game_id,
            tt_first.name AS game_name
        FROM (
            SELECT
                p.account_id,
                p.online_id AS player_name,
                MIN(tt.np_communication_id) AS first_np_communication_id
            FROM
                trophy_earned te
            JOIN player p ON p.account_id = te.account_id
            JOIN trophy_title tt ON tt.np_communication_id = te.np_communication_id
            WHERE
                __WHERE_CLAUSE__
                AND p.status != 1
            GROUP BY
                p.account_id,
                p.online_id
        ) AS first_games
        JOIN trophy_title tt_first ON tt_first.np_communication_id = first_games.first_np_communication_id
        ORDER BY
            first_games.player_name
        SQL;

        $sql = str_replace('__WHERE_CLAUSE__', $whereClause, $sql);

        $rows = $this->fetchAll($sql);

        return array_map(
            static fn(array $row): array => [
                'account_id' => (int) $row['account_id'],
                'player_name' => (string) $row['player_name'],
                'game_id' => (int) $row['game_id'],
                'game_name' => (string) $row['game_name'],
            ],
            $rows
        );
    }

    /**
     * @return PossibleCheaterRuleGroup[]
     */
    private function getGeneralRuleGroups(): array
    {
        if ($this->generalRuleGroups === null) {
            $this->generalRuleGroups = array_map(
                static fn(array $group): PossibleCheaterRuleGroup => PossibleCheaterRuleGroup::fromArray($group),
                self::GENERAL_RULE_GROUPS
            );
        }

        return $this->generalRuleGroups;
    }

    /**
     * @return PossibleCheaterSectionDefinition[]
     */
    private function getSectionDefinitions(): array
    {
        if ($this->sectionDefinitions === null) {
            $this->sectionDefinitions = array_map(
                static fn(array $definition): PossibleCheaterSectionDefinition => PossibleCheaterSectionDefinition::fromArray($definition),
                self::SECTION_DEFINITIONS
            );
        }

        return $this->sectionDefinitions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAll(string $sql): array
    {
        $statement = $this->database->prepare($sql);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }
}
