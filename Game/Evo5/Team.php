<?php

namespace App\GameModels\Game\Evo5;

class Team extends \App\GameModels\Game\Team
{

	public const TABLE      = 'evo5_teams';
	public const DEFINITION = [
		'game'     => ['class' => Game::class, 'validators' => ['required']],
		'color'    => ['validators' => ['required']],
		'score'    => ['default' => 0],
		'position' => ['default' => 0],
		'name'     => ['validators' => ['required']],
	];

	protected string $playerClass = Player::class;

}