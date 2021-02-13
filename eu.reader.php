<?

abstract class LogReader {
    private $data;
    private $pos = 0;
    protected function __construct($data) {
        $this->data = $data;
    }
    protected function end() {
        return $this->pos >> 3 >= strlen($this->data);
    }
    protected function readBool() {
        $result = $this->end() ? 0 : ord($this->data[$this->pos >> 3]) >> 7 - ($this->pos & 7) & 1;
        ++$this->pos;
        return $result;
    }
    protected function readFixed($bits) {
        $result = 0;
        while ($bits--)
            $result = $result << 1 | $this->readBool();
        return $result;
    }
    protected function readTally() {
        $result = 0;
        while ($this->readBool())
            ++$result;
        return $result;
    }
    protected function readFooter() {
        $size = $this->readFixed(2) << 3;
        $free = 8 - ($this->pos & 7) & 7;
        $size |= $free;
        $minimum = 0;
        while ($free < $size) {
            $minimum += 1 << $free;
            $free += 8;
        }
        return $this->readFixed($size) + $minimum;
    }
}

abstract class PlayerLogReader extends LogReader {
    protected function joinEvent($time, $newTeam) {}
    protected function quitEvent($time, $oldFlag, $oldPowers, $oldTeam) {}
    protected function switchEvent($time, $oldFlag, $powers, $newTeam) {}
    protected function grabEvent($time, $newFlag, $powers, $team) {}
    protected function captureEvent($time, $oldFlag, $powers, $team) {}
    protected function flaglessCaptureEvent($time, $flag, $powers, $team) {}
    protected function powerupEvent($time, $flag, $powerUp, $newPowers, $team) {}
    protected function duplicatePowerupEvent($time, $flag, $powers, $team) {}
    protected function powerdownEvent($time, $flag, $powerDown, $newPowers, $team) {}
    protected function returnEvent($time, $flag, $powers, $team) {}
    protected function tagEvent($time, $flag, $powers, $team) {}
    protected function dropEvent($time, $oldFlag, $powers, $team) {}
    protected function popEvent($time, $powers, $team) {}
    protected function startPreventEvent($time, $flag, $powers, $team) {}
    protected function stopPreventEvent($time, $flag, $powers, $team) {}
    protected function startButtonEvent($time, $flag, $powers, $team) {}
    protected function stopButtonEvent($time, $flag, $powers, $team) {}
    protected function startBlockEvent($time, $flag, $powers, $team) {}
    protected function stopBlockEvent($time, $flag, $powers, $team) {}
    protected function endEvent($time, $flag, $powers, $team) {}

    const noTeam = 0;
    const redTeam = 1;
    const blueTeam = 2;
    const noFlag = 0;
    const opponentFlag = 1;
    const opponentPotatoFlag = 2;
    const neutralFlag = 3;
    const neutralPotatoFlag = 4;
    const temporaryFlag = 5;
    const noPower = 0;
    const jukeJuicePower = 1;
    const rollingBombPower = 2;
    const tagProPower = 4;
    const topSpeedPower = 8;

    public function __construct($data, $team, $duration) {
        parent::__construct($data);
        $time    = 0;
        $flag    = self::noFlag;
        $powers  = self::noPower;
        $prevent = false;
        $button  = false;
        $block   = false;
        while (!$this->end()) {
            $newTeam    = $this->readBool() ? $team ? $this->readBool() ? self::noTeam : 3 - $team : 1 + $this->readBool() : $team; // quit : switch : join : stay
            $dropPop    = $this->readBool();
            $returns    = $this->readTally();
            $tags       = $this->readTally();
            $grab       = !$flag && $this->readBool();
            $captures   = $this->readTally();
            $keep       = !$dropPop && $newTeam && ($newTeam == $team || !$team) && (!$captures || (!$flag && !$grab) || $this->readBool());
            $newFlag    = $grab ? $keep ? 1 + $this->readFixed(2) : self::temporaryFlag : $flag;
            $powerups   = $this->readTally();
            $powersDown = self::noPower;
            $powersUp   = self::noPower;
            for ($i = 1; $i < 16; $i <<= 1)
                if ($powers & $i) {
                    if ($this->readBool())
                        $powersDown |= $i;
                } else if ($powerups && $this->readBool()) {
                    $powersUp |= $i;
                    $powerups--;
                }
            $togglePrevent = $this->readBool();
            $toggleButton  = $this->readBool();
            $toggleBlock   = $this->readBool();
            $time += 1 + $this->readFooter();
            if (!$team && $newTeam) {
                $team = $newTeam;
                $this->joinEvent($time, $team);
            }
            for ($i = 0; $i < $returns; $i++)
                $this->returnEvent($time, $flag, $powers, $team);
            for ($i = 0; $i < $tags; $i++)
                $this->tagEvent($time, $flag, $powers, $team);
            if ($grab) {
                $flag = $newFlag;
                $this->grabEvent($time, $flag, $powers, $team);
            }
            if ($captures--)
                do {
                    if ($keep || !$flag)
                        $this->flaglessCaptureEvent($time, $flag, $powers, $team);
                    else {
                        $this->captureEvent($time, $flag, $powers, $team);
                        $flag = self::noFlag;
                        $keep = true;
                    }
                } while ($captures--);
            for ($i = 1; $i < 16; $i <<= 1) {
                if ($powersDown & $i) {
                    $powers ^= $i;
                    $this->powerdownEvent($time, $flag, $i, $powers, $team);
                } else if ($powersUp & $i) {
                    $powers |= $i;
                    $this->powerupEvent($time, $flag, $i, $powers, $team);
                }
            }
            for ($i = 0; $i < $powerups; $i++)
                $this->duplicatePowerupEvent($time, $flag, $powers, $team);
            if ($togglePrevent) {
                if ($prevent) {
                    $this->stopPreventEvent($time, $flag, $powers, $team);
                    $prevent = false;
                } else {
                    $this->startPreventEvent($time, $flag, $powers, $team);
                    $prevent = true;
                }
            }
            if ($toggleButton) {
                if ($button) {
                    $this->stopButtonEvent($time, $flag, $powers, $team);
                    $button = false;
                } else {
                    $this->startButtonEvent($time, $flag, $powers, $team);
                    $button = true;
                }
            }
            if ($toggleBlock) {
                if ($block) {
                    $this->stopBlockEvent($time, $flag, $powers, $team);
                    $block = false;
                } else {
                    $this->startBlockEvent($time, $flag, $powers, $team);
                    $block = true;
                }
            }
            if ($dropPop) {
                if ($flag) {
                    $this->dropEvent($time, $flag, $powers, $team);
                    $flag = self::noFlag;
                } else
                    $this->popEvent($time, $powers, $team);
            }
            if ($newTeam != $team) {
                if (!$newTeam) {
                    $this->quitEvent($time, $flag, $powers, $team);
                    $powers = self::noPower;
                } else
                    $this->switchEvent($time, $flag, $powers, $newTeam);
                $flag = self::noFlag;
                $team = $newTeam;
            }
        }
        $this->endEvent($duration, $flag, $powers, $team);
    }
}

abstract class MapLogReader extends LogReader {
    protected function heightEvent($newY) {}
    protected function tileEvent($newX, $y, $tile) {}

    const emptyTile = 0;
    const squareWallTile = 10;
    const lowerLeftDiagonalWallTile = 11;
    const upperLeftDiagonalWallTile = 12;
    const upperRightDiagonalWallTile = 13;
    const lowerRightDiagonalWallTile = 14;
    const neutralFloorTile = 20;
    const redFlagTile = 30;
    const blueFlagTile = 40;
    const neutralSpeedpadTile = 50;
    const powerupTile = 60;
    const jukeJuicePowerupTile = 61;
    const rollingBombPowerupTile = 62;
    const tagProPowerupTile = 63;
    const topSpeedPowerupTile = 64;
    const spikeTile = 70;
    const buttonTile = 80;
    const openGateTile = 90;
    const closedGateTile = 91;
    const redGateTile = 92;
    const blueGateTile = 93;
    const bombTile = 100;
    const redFloorTile = 110;
    const blueFloorTile = 120;
    const entryPortalTile = 130;
    const exitPortalTile = 131;
    const redSpeedpadTile = 140;
    const blueSpeedpadTile = 150;
    const neutralFlagTile = 160;
    const temporaryFlagTile = 161; // just a dummy, cannot occur on maps
    const redEndzoneTile = 170;
    const blueEndzoneTile = 180;
    const redPotatoFlagTile = 190;
    const bluePotatoFlagTile = 200;
    const neutralPotatoFlagTile = 210;
    const marsballTile = 211; // just a dummy, cannot occur on maps
    const gravitywellTile = 220;
    const yellowFloorTile = 230;

    public function __construct($data, $width) {
        parent::__construct($data);
        $x = 0;
        $y = 0;
        while (!$this->end() || $x) {
            if ($tile = $this->readFixed(6)) {
                if ($tile < 6)
                    $tile += 9;
                else if ($tile < 13)
                    $tile = ($tile - 4) * 10;
                else if ($tile < 17)
                    $tile += 77;
                else if ($tile < 20)
                    $tile = ($tile - 7) * 10;
                else if ($tile < 22)
                    $tile += 110;
                else
                    $tile = ($tile - 8) * 10;
            }
            for ($i = 1 + $this->readFooter(); $i; $i--) {
                if (!$x)
                    $this->heightEvent($y);
                $this->tileEvent($x, $y, $tile);
                if (++$x == $width) {
                    $x = 0;
                    ++$y;
                }
            }
        }
    }
}

class PlayerEventHandler extends PlayerLogReader {
    protected function joinEvent($time, $newTeam) {
        global $player, $events;
        $events[$time][] = $player->name . '## joins team ' . $newTeam;
    }
    protected function quitEvent($time, $oldFlag, $oldPowers, $oldTeam) {
        global $player, $events;
        $events[$time][] = $player->name . '## quits team ' . $oldTeam;
    }
    protected function switchEvent($time, $oldFlag, $powers, $newTeam) {
        global $player, $events;
        $events[$time][] = $player->name . '## switches to team ' . $newTeam;
    }
    protected function grabEvent($time, $newFlag, $powers, $team) {
        global $player, $events;
        $events[$time][] = $player->name . '## grabs flag ' . $newFlag;
    }
    protected function captureEvent($time, $oldFlag, $powers, $team) {
        global $player, $events;
        $events[$time][] = $player->name . '## captures flag ' . $oldFlag;
    }
    protected function flaglessCaptureEvent($time, $flag, $powers, $team) {
        global $player, $events;
        $events[$time][] = $player->name . '## captures marsball';
    }
    protected function powerupEvent($time, $flag, $powerUp, $newPowers, $team) {
        global $player, $events;
        $events[$time][] = $player->name . '## powers up ' . $powerUp;
    }
    protected function duplicatePowerupEvent($time, $flag, $powers, $team) {
        global $player, $events;
        $events[$time][] = $player->name . '## extends power';
    }
    protected function powerdownEvent($time, $flag, $powerDown, $newPowers, $team) {
        global $player, $events;
        $events[$time][] = $player->name . '## powers down ' . $powerDown;
    }
    protected function returnEvent($time, $flag, $powers, $team) {
        global $player, $events;
        $events[$time][] = $player->name . '## returns';
    }
    protected function tagEvent($time, $flag, $powers, $team) {
        global $player, $events;
        $events[$time][] = $player->name . '## tags';
    }
    protected function dropEvent($time, $oldFlag, $powers, $team) {
        global $player, $events, $pops;
        $events[$time][]    = $player->name . '## drops flag ' . $oldFlag;
        $pops[$team][$time] = true;
    }
    protected function popEvent($time, $powers, $team) {
        global $player, $events, $pops;
        $events[$time][]    = $player->name . '## pops';
        $pops[$team][$time] = true;
    }
    protected function startPreventEvent($time, $flag, $powers, $team) {
        global $player, $events;
        $events[$time][] = $player->name . '## starts preventing';
    }
    protected function stopPreventEvent($time, $flag, $powers, $team) {
        global $player, $events;
        $events[$time][] = $player->name . '## stops preventing';
    }
    protected function startButtonEvent($time, $flag, $powers, $team) {
        global $player, $events;
        $events[$time][] = $player->name . '## starts buttoning';
    }
    protected function stopButtonEvent($time, $flag, $powers, $team) {
        global $player, $events;
        $events[$time][] = $player->name . '## stops buttoning';
    }
    protected function startBlockEvent($time, $flag, $powers, $team) {
        global $player, $events;
        $events[$time][] = $player->name . '## starts blocking';
    }
    protected function stopBlockEvent($time, $flag, $powers, $team) {
        global $player, $events;
        $events[$time][] = $player->name . '## stops blocking';
    }
    protected function endEvent($time, $flag, $powers, $team) {
        global $player, $events;
        if ($team)
            $events[$time][] = $player->name . '## ends in team ' . $team;
    }
    public function __construct() {
        global $match, $player, $events;
        if($player->team)
            $events[0][] = $player->name . '## starts in team ' . $player->team;
        parent::__construct(base64_decode($player->events), $player->team, $match->duration);
    }
}

class MapEventHandler extends MapLogReader {
    protected function heightEvent($newY) {
        global $mapHeight;
        // echo "\n";
        $mapHeight = $newY + 1;
    }
    protected function tileEvent($newX, $y, $tile) {

		if($tile === self::redFlagTile) {
			$this->flags[1] = [
				'y' => $y,
				'x' => $newX,
			];
		}
		if($tile === self::blueFlagTile) {
			$this->flags[2] = [
				'y' => $y,
				'x' => $newX,
			];
		}

        // switch ($tile) {
        //     case self::squareWallTile:
        //         echo '■';
        //         break;
        //     case self::lowerLeftDiagonalWallTile:
        //         echo '◣';
        //         break;
        //     case self::upperLeftDiagonalWallTile:
        //         echo '◤';
        //         break;
        //     case self::upperRightDiagonalWallTile:
        //         echo '◥';
        //         break;
        //     case self::lowerRightDiagonalWallTile:
        //         echo '◢';
        //         break;
        //     case self::redFlagTile:
        //     case self::blueFlagTile:
        //     case self::neutralFlagTile:
        //         echo '⚑';
        //         break;
        //     case self::neutralSpeedpadTile:
        //     case self::redSpeedpadTile:
        //     case self::blueSpeedpadTile:
        //         echo '⤧';
        //         break;
        //     case self::powerupTile:
        //         echo '◎';
        //         break;
        //     case self::spikeTile:
        //         echo '☼';
        //         break;
        //     case self::buttonTile:
        //         echo '•';
        //         break;
        //     case self::openGateTile:
        //     case self::closedGateTile:
        //     case self::redGateTile:
        //     case self::blueGateTile:
        //         echo '▦';
        //         break;
        //     case self::bombTile:
        //         echo '☢';
        //         break;
        //     default:
        //         echo ' ';
        // }
    }
    public function __construct() {
        global $match;
        parent::__construct(base64_decode($match->map->tiles), $match->map->width);
        echo "\n";
    }
}

function timeFormat($time) {
    return floor($time / 3600) . ':' . str_pad(floor($time % 3600 / 60), 2, '0', STR_PAD_LEFT) . '.' . str_pad(round($time % 60 / 0.6), 2, '0', STR_PAD_LEFT);
}

function arrayCastRecursive($array) {
    if (is_array($array))
        foreach ($array as $key => $value) {
            if (is_array($value))
				$array[$key] = arrayCastRecursive($value);
            if ($value instanceof stdClass)
                $array[$key] = arrayCastRecursive((array)$value);
        }

    if ($array instanceof stdClass)
        return arrayCastRecursive((array)$array);

    return $array;
}

////////
////////
////////
abstract class SplatLogReader extends LogReader {

	public $splash;

	public function __construct($data, $width, $height, $pops, $teamIndex) {
		parent::__construct($data);
		$y = $this->bits($height);
		$x = $this->bits($width);

		for($time = 0; !$this->end(); $time++) {
			if($i = $this->readTally()) {
				$splats = array();
				while($i--) {
					$splats[] = array($this->readFixed($x[0]) - $x[1], $this->readFixed($y[0]) - $y[1]);
				}
				$this->splash[$pops[$teamIndex][$time]] = $this->splatsEvent($splats, $time, $pops, $teamIndex);
			}
		}
	}

	private static function bits($size) {
		$size *= 40;
		$grid = $size - 1;
		$result = 32;
		if(!($grid & 0xFFFF0000)) { $result -= 16; $grid <<= 16; }
		if(!($grid & 0xFF000000)) { $result -=  8; $grid <<=  8; }
		if(!($grid & 0xF0000000)) { $result -=  4; $grid <<=  4; }
		if(!($grid & 0xC0000000)) { $result -=  2; $grid <<=  2; }
		if(!($grid & 0x80000000)) $result--;
		return array($result, ((1 << $result) - $size >> 1) + 20);
	}

}

class SplatEventHandler extends SplatLogReader {

	public function __construct($match, $mapHeight, $pops, $team, $i) {
		$splats = array();
		parent::__construct(base64_decode($team->splats), $match->map->width, $mapHeight, $pops, $i);
	}

	protected function splatsEvent($splats, $time, $pops, $teamIndex) {
		foreach($splats as $splat) {
			$x = $splat[0];
			$y = $splat[1];
			$t = $pops[$teamIndex][$time];
			// $t = timeFormat($pops[$teamIndex][$time]);

			return [
				'y' => $y / 40,
				'x' => $x / 40,
			];
		}
	}

}
