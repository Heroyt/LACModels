<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Evo5;

use App\GameModels\Factory\TeamFactory;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\NoDB;
use Lsr\Core\Models\Attributes\PrimaryKey;

/**
 * LaserMaxx Evo5 team model
 */
#[PrimaryKey('id_team')]
#[Factory(TeamFactory::class, ['system' => 'evo5'])] // @phpstan-ignore-line
class Team extends \App\GameModels\Game\Team
{

	public const TABLE  = 'evo5_teams';
	public const SYSTEM = 'evo5';

	#[NoDB]
	public string $playerClass = Player::class;

	#[ManyToOne(class: Game::class)]
	public \App\GameModels\Game\Game $game;

}