<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game;

use Dibi\Exception;
use JsonSerializable;
use Lsr\Db\DB;
use Lsr\Helpers\Tools\Timer;

/**
 * Data model for player hits
 *
 * N:M relation between players indicating how many times did player1 hit player 2
 */
class PlayerHit implements JsonSerializable
{
    public const string TABLE = '';

    public function __construct(
      public Player $playerShot,
      public Player $playerTarget,
      public int    $count = 0
    ) {}

    /**
     * @return bool
     */
    public function save() : bool {
        Timer::start('player.hits.check');
        $test = DB::select($this::TABLE, '*')->where(
          '[id_player] = %i AND [id_target] = %i',
          $this->playerShot->id,
          $this->playerTarget->id
        )->fetch();
        Timer::stop('player.hits.check');
        $data = $this->getQueryData();
        try {
            Timer::start('player.hits.insertUpdate');
            if (isset($test)) {
                DB::update(
                  $this::TABLE,
                  $data,
                  ['[id_player] = %i AND [id_target] = %i', $this->playerShot->id, $this->playerTarget->id]
                );
            }
            else {
                DB::insert($this::TABLE, $data);
            }
            Timer::stop('player.hits.insertUpdate');
        } catch (Exception) {
            return false;
        }
        return true;
    }

    /**
     * @return array{id_player:int|null,id_target:int|null,count:int|null}
     * @noinspection PhpArrayShapeAttributeCanBeAddedInspection
     */
    public function getQueryData() : array {
        return [
          'id_player' => $this->playerShot->id,
          'id_target' => $this->playerTarget->id,
          'count'     => $this->count,
        ];
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @link         http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return array<string,mixed> data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since        5.4.0
     * @noinspection PhpArrayShapeAttributeCanBeAddedInspection
     */
    public function jsonSerialize() : array {
        return [
          'shot'   => $this->playerShot->id,
          'target' => $this->playerTarget->id,
          'count'  => $this->count,
        ];
    }
}
