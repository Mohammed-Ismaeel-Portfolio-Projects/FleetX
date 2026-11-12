<?php

namespace App\Simulation\Routing\Enums;

/**
 * InstructionType Enum.
 * Represents the type of navigation instruction.
 */
enum InstructionType: string
{
    case TURN_LEFT = 'turn_left';
    case TURN_RIGHT = 'turn_right';
    case TURN_SLIGHT_LEFT = 'turn_slight_left';
    case TURN_SLIGHT_RIGHT = 'turn_slight_right';
    case CONTINUE_STRAIGHT = 'continue_straight';
    case U_TURN = 'u_turn';
    case MERGE = 'merge';
    case RAMP = 'ramp';
    case ROUNDABOUT_ENTER = 'roundabout_enter';
    case ROUNDABOUT_EXIT = 'roundabout_exit';
    case ROUNDABOUT_STRAIGHT = 'roundabout_straight';
    case FERRY_ENTER = 'ferry_enter';
    case FERRY_EXIT = 'ferry_exit';
    case ARRIVE = 'arrive';
    case DEPART = 'depart';
    case HEAD_ON = 'head_on';
    case END_OF_ROAD = 'end_of_road';
    case UNKNOWN = 'unknown';
}
