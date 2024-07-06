<?php

namespace App\GameModels\Game\LaserForce\Enums;

/**
 *
 */
enum EventType: string
{
    // SYSTEM
    case START = 'start';
    case END = 'end';

    // HITS
    case HIT = 'hit';
    case HIT_OWN = 'hit_own';
    case TARGET_HIT = 'target_hit';
    case TARGET_DESTROYED = 'target';
    case TARGET_ROCKET_DESTROYED = 'target_rocket';
    case TARGETS = 'targets';
    case ROCKET_MISS = 'rocket_miss';
    case ROCKET = 'rocket';

    // SHOTS
    case TARGET_MISS = 'target_miss';
    case MISS = 'miss';

    // OTHER
    case ACHIEVEMENT = 'achievement';
    case LEVEL_UP = 'level_up';
    case PUNISHED = 'punished';

    // POWER
    case MACHINE_GUN = 'machine_gun';
    case INVINCIBILITY = 'invincibility';
    case NUKE_START = 'nuke_start';
    case NUKE = 'nuke';
    case PAYBACK = 'payback';
    case RESET = 'reset';
    case SHIELD = 'shield';

    // SPACE MARINES
    case ADD_LIVES = 'add_lives';
    case ADD_TEAM_LIVES = 'add_team_lives';
    case ADD_AMMO = 'add_ammo';
    case ADD_TEAM_AMMO = 'add_team_ammo';

    // Different game mode actions
    /**
     * LaserBall - pass
     * Tag - expire
     * Color conquest - team change
     * Revolver - out of ammo
     * Zombie - infect
     */
    case MODE_ACTION_1 = 'mode_action_1';
    /**
     * LaserBall - goal
     * Tag - got tagged
     * Color conquest - team eliminated
     * Revolver - fill up
     * Zombie - became a zombie
     * Shadows - Switched to shooting
     */
    case MODE_ACTION_2 = 'mode_action_2';
    /**
     * LaserBall - win
     * Color conquest - win
     * LaserBall Mayhem - scores at target
     * Shadows - Switched to rockets
     */
    case MODE_ACTION_3 = 'mode_action_3';
    /**
     * LaserBall - steal
     */
    case MODE_ACTION_4 = 'mode_action_4';
    /**
     * LaserBall - block
     * Zombie - uses healing
     */
    case MODE_ACTION_5 = 'mode_action_5';
    /**
     * LaserBall - round start
     * Zombie - collects healing
     */
    case MODE_ACTION_6 = 'mode_action_6';
    /**
     * LaserBall - round end
     */
    case MODE_ACTION_7 = 'mode_action_7';
    /**
     * LaserBall - gets the ball
     */
    case MODE_ACTION_8 = 'mode_action_8';
    /**
     * LaserBall - ran out of time
     */
    case MODE_ACTION_9 = 'mode_action_9';
    /**
     * LaserBall - clear
     */
    case MODE_ACTION_10 = 'mode_action_10';

    // Domination
    case BEACON = 'beacon';

    public static function getForType(string $type): ?EventType {
        return match ($type) {
            '0E00' => self::LEVEL_UP,
            '0100' => self::START,
            '0101' => self::END,
            '0201' => self::MISS,
            '0202' => self::TARGET_MISS,
            '0203' => self::TARGET_HIT,
            '0204' => self::TARGET_DESTROYED,
            '0205', '0206' => self::HIT,
            '0208' => self::HIT_OWN,
            '0300' => self::TARGETS,
            '0301' => self::ROCKET_MISS,
            '0303' => self::TARGET_ROCKET_DESTROYED,
            '0306' => self::ROCKET,
            '0400' => self::MACHINE_GUN,
            '0402' => self::INVINCIBILITY,
            '0404' => self::NUKE_START,
            '0405' => self::NUKE,
            '0408' => self::PAYBACK,
            '0409' => self::RESET,
            '040A' => self::SHIELD,
            '0500' => self::ADD_LIVES,
            '0502' => self::ADD_AMMO,
            '0510' => self::ADD_TEAM_LIVES,
            '0512' => self::ADD_TEAM_AMMO,
            '0600' => self::PUNISHED,
            '0900' => self::ACHIEVEMENT,
            '0B00' => self::BEACON,
            '1100' => self::MODE_ACTION_1,
            '1101' => self::MODE_ACTION_2,
            '1102' => self::MODE_ACTION_3,
            '1103' => self::MODE_ACTION_4,
            '1104' => self::MODE_ACTION_5,
            '1105' => self::MODE_ACTION_6,
            '1106' => self::MODE_ACTION_7,
            '1107' => self::MODE_ACTION_8,
            '1108' => self::MODE_ACTION_9,
            '1109' => self::MODE_ACTION_10,
            default => null,
        };
    }

    public function isModeAction(): bool {
        return match ($this) {
            self::MODE_ACTION_1, self::MODE_ACTION_2, self::MODE_ACTION_3, self::MODE_ACTION_4, self::MODE_ACTION_5, self::MODE_ACTION_6, self::MODE_ACTION_7, self::MODE_ACTION_8, self::MODE_ACTION_9, self::MODE_ACTION_10 => true,
            default => false,
        };
    }
}
