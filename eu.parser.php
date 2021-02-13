<?

class euParser {

	private $tmp;
	private $active;

	public $play_minutes;
	public $grab;
	public $cap;
	public $drop;
	public $return;
	public $return_streak;
	public $tag;
	public $tag_streak;
	public $pop;
	public $pup;
	public $hold_minutes;
	public $pup_minutes;
	public $prevent_minutes;
	public $block_minutes;
	public $button_minutes;
	public $kept_flag;
	public $score;
	public $flair;
	public $authenticated;
	public $degree;
	public $team;
	public $flaccid;
	public $quick_return;
	public $long_hold;
	public $long_hold_and_cap;
	public $super_hold;
	public $super_hold_and_cap;
	public $wtl_positions;
	public $wtl_minutes;
	// public $regrab;
	// public $handoff;
	public $good_handoff;
	public $key_return;
	public $reset;
	public $cap_from_prevent;
	public $cap_from_block;
	public $cap_from_my_prevent;
	public $cap_from_my_block;
	public $cap_from_handoff;
	public $cap_from_regrab;
	public $kiss;
	public $bad_kiss;
	public $good_kiss;
	public $prevent_whilst_team_hold_time;
	public $hold_whilst_team_prevent_time;
	public $hold_whilst_opponents_dont;
	public $hold_whilst_opponents_do;
	public $return_from_button;
	public $tag_from_button;
	public $cap_from_my_handoff;
	public $longest_hold;
	public $prevent_whilst_team_hold;
	public $hold_whilst_team_prevent;
	public $hold_whilst_prevent;
	public $return_within_5_tiles_from_opponents_base;
	public $return_within_2_tiles_from_opponents_base;
	public $drop_within_my_half;
	public $drop_within_5_tiles_from_my_base;
	public $drop_within_2_tiles_from_my_base;
	public $tag_within_my_half;
	public $pop_within_my_half;
	public $return_within_my_half;
	public $grab_whilst_opponents_hold;
	public $grab_whilst_opponents_hold_team_for;
	public $grab_whilst_opponents_hold_team_against;
	public $assists;
	public $game_won;
	public $hold_before_cap;

#	active player
# --------------------------------------------------------------------------

	public function active($player, $team, $time) {
		$this->active[$player] = $team;

		$this->time($player, $time);
	}

	public function deactive($player) {
		unset($this->active[$player]);
	}

#	time
# --------------------------------------------------------------------------

	public function time($player, $time) {
		$this->time_start($player, $time);
		$this->wtl_start($player, $time);
	}

	public function time_start($player, $time) {
		$this->tmp['time_start'][$player] = $time;
	}

	public function time_stop($player, $time) {
		$this->play_minutes['player'][$player] += ($time - $this->tmp['time_start'][$player]);

		unset($this->tmp['time_start'][$player]);

		if(isset($tmp['hold']['player'][$player]))
			$this->hold($player, $time);
	}

#	grabs
# --------------------------------------------------------------------------

	public function grab($player, $time) {
		$this->grab['player'][$player]++;

		foreach($this->active as $p => $pt)
			if($pt == $this->active[$player])
				$this->grab['team']['for'][$p]++;
			else
				$this->grab['team']['against'][$p]++;

		$this->grab_whilst_opponents_hold($player, $time);
		$this->hold($player, $time);
	}

#	hold before cap
# --------------------------------------------------------------------------

	public function hold_before_cap($player, $time) {
		$this->hold_before_cap['player'][$player] += ($time - $this->tmp['hold_start'][$player]);
	}

#	hold
# --------------------------------------------------------------------------

	public function hold($player, $time) {
		$this->hold_start($player, $time);
	}

	public function hold_start($player, $time) {
		$this->tmp['hold_start'][$player] = $time;

		$this->trigger_prevent_whilst_team_hold_start($player, $time);
		$this->hold_whilst_team_prevent_start_time($player, $time);
		$this->hold_whilst_opponents_dont_start($player, $time);
		$this->hold_whilst_opponents_do_start($player, $time);

		$this->grab_whilst_opponents_prevent($player, $time);

		$team = $this->getTeam($player);
		$this->tmp['hold'][$team][$player]['start'] = $time;
	}

	public function hold_stop($player, $time, $events = false, $pop = true, $cap = false) {
		unset($this->tmp['grab_whilst_opponents_prevent_active'][$player]);
		unset($this->tmp['regrab_active'][$player]);
		unset($this->tmp['handoff_active'][$player]);

		$this->hold_minutes['player'][$player] += ($time - $this->tmp['hold_start'][$player]);

		foreach($this->active as $p => $pt)
			if($pt == $this->active[$player])
				$this->hold_minutes['team']['for'][$p] += ($time - $this->tmp['hold_start'][$player]);
			else
				$this->hold_minutes['team']['against'][$p] += ($time - $this->tmp['hold_start'][$player]);

		$this->long_hold($player, $time, $cap);

		// do not increment if hold_stop is NOT a pop. i.e. kept flag or cap
		if($pop)
			$this->pop($player, $time);

		$team = $this->getTeam($player);
		$this->tmp['hold'][$team][$player]['stop'] = $time;

		$fc_spiked = true;
		foreach($events as $tE) {
			$_player = explode('## ', $tE)[0];
			$_msg = explode('## ', $tE)[1];

			if($this->getTeam($_player) != $this->getTeam($player) && $_msg === 'returns') {
				$this->tmp['hold'][$team][$player]['returned_by'] = $_player;
				$fc_spiked = false;
			}
		}

		if($fc_spiked === true)
			unset($this->tmp['hold'][$team][$player]['returned_by']);

		$this->kiss_status($player);

		$this->tiles_travelled_whilst_hold($player, $time);
		$this->longest_hold($player, $time);
		$this->trigger_prevent_whilst_team_hold_stop($player, $time);
		$this->hold_whilst_team_prevent_stop_time($player, $time);
		$this->hold_whilst_opponents_dont_stop($player, $time);
		$this->hold_whilst_opponents_do_stop($player, $time);

		unset($this->tmp['hold_start'][$player]);
	}

	public function long_hold($player, $time, $cap = false) {
		$hold_start = $this->tmp['hold_start'][$player];
		$hold = ($time - $hold_start);
		$hold_seconds = $this->seconds($hold);

		if($hold_seconds >= 20) {
			$this->long_hold['player'][$player]++;

			if($cap)
				$this->long_hold_and_cap['player'][$player]++;

			foreach($this->active as $p => $pt)
				if($pt == $this->active[$player]) {
					$this->long_hold['team']['for'][$p]++;
					if($cap)
						$this->long_hold_and_cap['team']['for'][$p]++;
				}
				else {
					$this->long_hold['team']['against'][$p]++;
					if($cap)
						$this->long_hold_and_cap['team']['against'][$p]++;
				}
		}

		if($hold_seconds >= 60) {
			$this->super_hold['player'][$player]++;

			if($cap)
				$this->super_hold_and_cap['player'][$player]++;

			foreach($this->active as $p => $pt)
				if($pt == $this->active[$player]) {
					$this->super_hold['team']['for'][$p]++;
					if($cap)
						$this->super_hold_and_cap['team']['for'][$p]++;
				}
				else {
					$this->super_hold['team']['against'][$p]++;
					if($cap)
						$this->super_hold_and_cap['team']['against'][$p]++;
				}

		}
	}

#	grabs whilst opponents prevent
# --------------------------------------------------------------------------

	public function grab_whilst_opponents_prevent($player, $time) {
		$isPreventing = $this->isOpponentTeamPreventing($player);
		if($isPreventing) {
			// check other team is preventing and preventing longer than 3 seconds?
			$stop = false;
			foreach($this->tmp['prevent_start'] as $p => $pt)
				if($this->getTeam($player) != $this->getTeam($p) && $player != $p)
					if($this->seconds($time - $pt) >= 2)
						$stop = true;

			if($stop) return false;

			$this->tmp['grab_whilst_opponents_prevent_active'][$player] = true;

			$this->grab_whilst_opponents_prevent['player'][$player]++;

			foreach($this->active as $p => $pt)
				if($pt == $this->active[$player])
					$this->grab_whilst_opponents_prevent['team']['for'][$p]++;
				else
					$this->grab_whilst_opponents_prevent['team']['against'][$p]++;

			// opponents grab whilst I prevent
			foreach($this->tmp['prevent_start'] as $_p => $_pt)
				if($this->getTeam($player) != $this->getTeam($_p))
					$this->opponents_grab_whilst_my_prevent['player'][$_p]++;
		}
	}


#	grab whilt opponents hold
# --------------------------------------------------------------------------

	public function grab_whilst_opponents_hold($player, $time) {
		if($this->isOpponentTeamHolding($player)) {
			$this->grab_whilst_opponents_hold['player'][$player]++;

			foreach($this->active as $p => $pt)
				if($pt == $this->active[$player])
					$this->grab_whilst_opponents_hold['team']['for'][$p]++;
				else
					$this->grab_whilst_opponents_hold['team']['against'][$p]++;
		}

		// if hold longest than 5 seconds
		if($this->isOpponentTeamHolding($player)) {
			foreach($this->tmp['hold_start'] as $p => $pt)
				if($this->getTeam($player) != $this->getTeam($p) && $player != $p)
					$opponentPlayer = $p;

			$opponent_hold_time = $this->seconds($time - $this->tmp['hold_start'][$p]);
			if($opponent_hold_time > 5) {
				$this->grab_whilst_opponents_hold_long['player'][$player]++;

				foreach($this->active as $p => $pt)
					if($pt == $this->active[$player])
						$this->grab_whilst_opponents_hold_long['team']['for'][$p]++;
					else
						$this->grab_whilst_opponents_hold_long['team']['against'][$p]++;
			}
		}
	}


#	longest hold
# --------------------------------------------------------------------------

	public function longest_hold($player, $time) {
		$hold = ($time - $this->tmp['hold_start'][$player]);

		if($hold > $this->longest_hold['player'][$player])
			$this->longest_hold['player'][$player] = $hold;
	}

#	caps
# --------------------------------------------------------------------------

	public function cap($player, $time) {
		$this->cap['player'][$player]++;

		foreach($this->active as $p => $pt)
			if($pt == $this->active[$player])
				$this->cap['team']['for'][$p]++;
			else
				$this->cap['team']['against'][$p]++;

		$this->cap_whilst_having_active_pup($player, $time);
		// $this->cap_whilst_teammate_has_active__pup();

		$this->handoff($player, $time);
		$this->hold_before_cap($player, $time);
		$this->cap_from_handoff($player, $time);
		$this->cap_from_regrab($player, $time);
		$this->cap_from_grab_whilst_opponents_prevent($player, $time);
		$this->cap_from_prevent($player, $time);
		$this->cap_from_block($player, $time);
		$this->wtl_positions($player, $time);
		$this->key_return($player, $time);
		$this->hold_stop($player, $time, false, false, true);
		$this->assist($player);
	}

#	caps from grabs whilst opponents prevent
# --------------------------------------------------------------------------

	public function cap_from_grab_whilst_opponents_prevent($player, $time) {
		if(isset($this->tmp['grab_whilst_opponents_prevent_active'][$player])) {
			$this->cap_from_grab_whilst_opponents_prevent['player'][$player]++;

			foreach($this->active as $p => $pt)
				if($pt == $this->active[$player])
					$this->cap_from_grab_whilst_opponents_prevent['team']['for'][$p]++;
				else
					$this->cap_from_grab_whilst_opponents_prevent['team']['against'][$p]++;
		}
	}

#	scoreboard
# --------------------------------------------------------------------------

	public function scoreboard($player) {
		$team = $this->getTeam($player);
		$this->tmp['scoreboard'][$team]++;
	}

	// init scores from previous half
	public function setScoreboard($half, $red, $blue) {
		if($half === 1) {
			$this->tmp['scoreboard'][1] += $red | 0;
			$this->tmp['scoreboard'][2] += $blue | 0;
		}
		else {
			$this->tmp['scoreboard'][1] += $blue | 0;
			$this->tmp['scoreboard'][2] += $red | 0;
		}
	}

#	drops
# --------------------------------------------------------------------------

	public function drop($player, $time, $events) {
		$this->drop['player'][$player]++;

		foreach($this->active as $p => $pt)
			if($pt == $this->active[$player])
				$this->drop['team']['for'][$p]++;
			else
				$this->drop['team']['against'][$p]++;

		$this->drop_within_my_half($player, $time);
		$this->drop_within_5_tiles_from_my_base($player, $time);
		$this->drop_within_2_tiles_from_my_base($player, $time);
		$this->flaccid($player, $time, $events);
		$this->kiss($player, $time, $events);
		$this->handoff($player, $time);
		$this->hold_stop($player, $time, $events);
	}

# 	drop within 2 tiles from my base
# --------------------------------------------------------------------------

	public function drop_within_2_tiles_from_my_base($player, $time) {
		if($this->tilesAwayFromCapping($player, $time, 2))
			$this->drop_within_2_tiles_from_my_base['player'][$player]++;
	}

# 	drop within 5 tiles from my base
# --------------------------------------------------------------------------

	public function drop_within_5_tiles_from_my_base($player, $time) {
		if($this->tilesAwayFromCapping($player, $time, 5))
			$this->drop_within_5_tiles_from_my_base['player'][$player]++;
	}

# 	drop in my base
# --------------------------------------------------------------------------

	public function drop_within_my_half($player, $time) {
		if($this->isSplatMyHalf($player, $time)) {
			$this->drop_within_my_half['player'][$player]++;

			foreach($this->active as $p => $pt)
				if($pt == $this->active[$player])
					$this->drop_within_my_half['team']['for'][$p]++;
				else
					$this->drop_within_my_half['team']['against'][$p]++;
		}
	}

# 	key return
# --------------------------------------------------------------------------

	public function key_return($player, $time) {
		$team = $this->getTeam($player);
		$other_team = ($team == '1') ? 2 : 1;

		foreach($this->tmp['hold'][$other_team] as $_p => $arr) {
			if($_p != $player) {
				$seconds_between_team_return_and_cap = $this->seconds($time - $arr['stop']);

				if($seconds_between_team_return_and_cap < 3) {
					// if doesn't exist than assume spike
					if(isset($arr['returned_by'])) {
						$returned_by = $arr['returned_by'];
						$this->key_return['player'][$returned_by]++;
						$this->tmp['assist'][$returned_by] = true;

						foreach($this->active as $p => $pt)
							if($pt == $this->active[$returned_by])
								$this->key_return['team']['for'][$p]++;
							else
								$this->key_return['team']['against'][$p]++;
					}
				}
			}
		}
	}

#	handoff & regrab
# --------------------------------------------------------------------------

	public function handoff($player, $time) {
		$team = $this->getTeam($player);
		$player_start = $this->tmp['hold'][$team][$player]['start'];

		$regrab_hold = $this->seconds($time - $player_start);

		foreach($this->tmp['hold'][$team] as $_p => $arr) {
			if($_p != $player) {

				$seconds_between_drop_and_grab = $this->seconds($player_start - $arr['stop']);
				$handoff_hold = $this->seconds($arr['stop'] - $arr['start']);

				/*
				 * handoff = drop and <= 3 seconds hold
				 * regrab = drop and > 3 seconds hold
				 */

				// regrab
				if($seconds_between_drop_and_grab < 2 && $handoff_hold >= 3) {

					$this->regrab_pickup['player'][$player]++;
					$this->regrab_drop['player'][$_p]++;

					foreach($this->active as $p => $pt)
						if($pt == $this->active[$player])
							$this->regrab['team']['for'][$p]++;
						else
							$this->regrab['team']['against'][$p]++;

					// good
					if($regrab_hold > 5) {
						$this->good_regrab_pickup['player'][$player]++;
						$this->good_regrab_drop['player'][$_p]++;

						foreach($this->active as $p => $pt)
							if($pt == $this->active[$player])
								$this->good_regrab['team']['for'][$p]++;
							else
								$this->good_regrab['team']['against'][$p]++;
					}

					$this->tmp['regrab_active'][$player] = $_p;
				}

				// handoff
				else if($seconds_between_drop_and_grab < 2 && $handoff_hold < 3) {

					$this->handoff_pickup['player'][$player]++;
					$this->handoff_drop['player'][$_p]++;

					foreach($this->active as $p => $pt)
						if($pt == $this->active[$player])
							$this->handoff['team']['for'][$p]++;
						else
							$this->handoff['team']['against'][$p]++;

					// good
					if($regrab_hold > 5) {
						$this->good_handoff_pickup['player'][$player]++;
						$this->good_handoff_drop['player'][$_p]++;

						foreach($this->active as $p => $pt)
							if($pt == $this->active[$player])
								$this->good_handoff['team']['for'][$p]++;
							else
								$this->good_handoff['team']['against'][$p]++;
					}

					$this->tmp['handoff_active'][$player] = $_p;
				}

			}
		}
	}

#	flaccid
# --------------------------------------------------------------------------

	public function flaccid($player, $time, $events) {
		$hold_start = $this->tmp['hold_start'][$player];
		$hold = ($time - $hold_start);
		$hold_seconds = $this->seconds($hold);

		if($hold_seconds < 2) {
			$this->flaccid['player'][$player]++;

			foreach($this->active as $p => $pt)
				if($pt == $this->active[$player])
					$this->flaccid['team']['for'][$p]++;
				else
					$this->flaccid['team']['against'][$p]++;

			$this->quick_return($player, $time, $events);
		}
	}

#	quick return
# --------------------------------------------------------------------------

	public function quick_return($player, $time, $events) {
		foreach($events as $tE) {
			$_player = explode('## ', $tE)[0];
			$_msg = explode('## ', $tE)[1];

			if($player === $_player)
				continue;
			else if($this->getTeam($_player) != $this->getTeam($player) && $_msg === 'returns') {
				$this->quick_return['player'][$_player]++;

				foreach($this->active as $p => $pt)
					if($pt == $this->active[$_player])
						$this->quick_return['team']['for'][$p]++;
					else
						$this->quick_return['team']['against'][$p]++;

			}
		}
	}

#	kiss
# --------------------------------------------------------------------------

	public function kiss($player, $time, $events) {
		foreach($events as $tE) {
			$_player = explode('## ', $tE)[0];
			$_msg = explode('## ', $tE)[1];

			if($_msg === 'drops flag 1' && $_player != $player) {
				$this->tmp['kiss'] = [
					'time' => $time,
					'players' => [$player, $_player],
				];

				$this->kiss['player'][$player]++;

				foreach($this->active as $p => $pt)
					if($pt == $this->active[$player])
						$this->kiss['team']['for'][$p]++;
					else
						$this->kiss['team']['against'][$p]++;
			}
		}
	}

#	kiss status: good/bad
# --------------------------------------------------------------------------

	public function kiss_status($player) {
		if(!isset($this->tmp['kiss']['time'])) return;

		$kiss_time = $this->tmp['kiss']['time'];
		$hold_start_time = $this->tmp['hold_start'][$player];
		$time_between_kiss_and_hold_start = (int)$this->seconds($hold_start_time - $kiss_time);

		if($time_between_kiss_and_hold_start < 2 && $time_between_kiss_and_hold_start >= 0) {

			// check if the other hold_start entry fits the < 2 logic
			// and if so, cancel out the kiss
			if(count($this->tmp['hold_start']) === 2) {
				$temp = $this->tmp['hold_start'];
				unset($temp[$player]);
				foreach($temp as $p => $hold_start_time_2) {
					$time_between_kiss_and_hold_start_2 = (int)$this->seconds($hold_start_time_2 - $kiss_time);
					if($time_between_kiss_and_hold_start_2 < 2 && $time_between_kiss_and_hold_start_2 >= 0)
						return;
				}
			}

			foreach($this->tmp['kiss']['players'] as $i => $kiss_player)
				if($this->getTeam($player) === $this->getTeam($kiss_player))
					$this->good_kiss['player'][$kiss_player]++;
				else
					$this->bad_kiss['player'][$kiss_player]++;

			foreach($this->active as $p => $pt)
				if($pt == $this->active[$player])
					$this->good_kiss['team'][$p]++;
				else
					$this->bad_kiss['team'][$p]++;

			unset($this->tmp['kiss']);
		}
	}

#	returns
# --------------------------------------------------------------------------

	public function return($player, $time) {
		$this->return['player'][$player]++;

		foreach($this->active as $p => $pt)
			if($pt == $this->active[$player])
				$this->return['team']['for'][$p]++;
			else
				$this->return['team']['against'][$p]++;

		$this->return_within_5_tiles_from_opponents_base($player, $time);
		$this->return_within_2_tiles_from_opponents_base($player, $time);
		$this->return_within_my_half($player, $time);
		$this->tag($player, $time);
		$this->return_from_button($player);
		$this->return_streak_start($player);
	}

# 	od return = return that is 5 tiles away from conceding cap
# --------------------------------------------------------------------------

	public function return_within_5_tiles_from_opponents_base($player, $time) {
		if($this->tilesAwayFromConceding($player, $time, 5)) {
			$this->return_within_5_tiles_from_opponents_base['player'][$player]++;

			foreach($this->active as $p => $pt)
				if($pt == $this->active[$player])
					$this->return_within_5_tiles_from_opponents_base['team']['for'][$p]++;
				else
					$this->return_within_5_tiles_from_opponents_base['team']['against'][$p]++;
			}
	}

# 	save return = return that is 2 tiles away from conceding cap
# --------------------------------------------------------------------------

	public function return_within_2_tiles_from_opponents_base($player, $time) {
		if($this->tilesAwayFromConceding($player, $time, 2)) {
			$this->return_within_2_tiles_from_opponents_base['player'][$player]++;

			foreach($this->active as $p => $pt)
				if($pt == $this->active[$player])
					$this->return_within_2_tiles_from_opponents_base['team']['for'][$p]++;
				else
					$this->return_within_2_tiles_from_opponents_base['team']['against'][$p]++;
			}
	}

# 	return in my half
# --------------------------------------------------------------------------

	public function return_within_my_half($player, $time) {
		if($this->isSplatMyHalf($player, $time, true)) {

			$this->return_within_my_half['player'][$player]++;

			foreach($this->active as $p => $pt)
				if($pt == $this->active[$player])
					$this->return_within_my_half['team']['for'][$p]++;
				else
					$this->return_within_my_half['team']['against'][$p]++;
		}
	}

#	return from button
# --------------------------------------------------------------------------

	public function return_from_button($player) {
		if($this->isButtoning($player)) {
			$this->return_from_button['player'][$player]++;

			foreach($this->active as $p => $pt)
				if($pt == $this->active[$player])
					$this->return_from_button['team']['for'][$p]++;
				else
					$this->return_from_button['team']['against'][$p]++;
		}
	}

#	best return streak
# --------------------------------------------------------------------------

	public function return_streak_start($player) {
		$this->tmp['return_streak'][$player]++;
	}

	public function return_streak_stop($player) {
		if($this->tmp['return_streak'][$player] > $this->return_streak['player'][$player])
			$this->return_streak['player'][$player] = $this->tmp['return_streak'][$player];
		$this->tmp['return_streak'][$player] = 0;
	}

#	tags
# --------------------------------------------------------------------------

	public function tag($player, $time) {
		$this->tag['player'][$player]++;

		foreach($this->active as $p => $pt)
			if($pt == $this->active[$player])
				$this->tag['team']['for'][$p]++;
			else
				$this->tag['team']['against'][$p]++;

		$this->tag_within_my_half($player, $time);
		$this->tag_from_button($player);
		$this->tag_streak_start($player);
	}

# 	tags in my half
# --------------------------------------------------------------------------

	public function tag_within_my_half($player, $time) {
		if($this->isSplatMyHalf($player, $time, true)) {

			$this->tag_within_my_half['player'][$player]++;

			foreach($this->active as $p => $pt)
				if($pt == $this->active[$player])
					$this->tag_within_my_half['team']['for'][$p]++;
				else
					$this->tag_within_my_half['team']['against'][$p]++;
		}
	}

#	return from button
# --------------------------------------------------------------------------

	public function tag_from_button($player) {
		if($this->isButtoning($player)) {
			$this->tag_from_button['player'][$player]++;

			foreach($this->active as $p => $pt)
				if($pt == $this->active[$player])
					$this->tag_from_button['team']['for'][$p]++;
				else
					$this->tag_from_button['team']['against'][$p]++;
		}
	}


#	best tag streak
# --------------------------------------------------------------------------

	public function tag_streak_start($player) {
		$this->tmp['tag_streak'][$player]++;
	}

	public function tag_streak_stop($player) {
		if($this->tmp['tag_streak'][$player] > $this->tag_streak['player'][$player])
			$this->tag_streak['player'][$player] = $this->tmp['tag_streak'][$player];
		$this->tmp['tag_streak'][$player] = 0;
	}

#	pops
# --------------------------------------------------------------------------

	public function pop($player, $time) {
		$this->pop['player'][$player]++;

		foreach($this->active as $p => $pt)
			if($pt == $this->active[$player])
				$this->pop['team']['for'][$p]++;
			else
				$this->pop['team']['against'][$p]++;

		$this->pop_within_my_half($player, $time);
		$this->tag_streak_stop($player);
		$this->return_streak_stop($player);
	}

# 	pop in my half
# --------------------------------------------------------------------------

	public function pop_within_my_half($player, $time) {
		if($this->isSplatMyHalf($player, $time)) {

			$this->pop_within_my_half['player'][$player]++;

			foreach($this->active as $p => $pt)
				if($pt == $this->active[$player])
					$this->pop_within_my_half['team']['for'][$p]++;
				else
					$this->pop_within_my_half['team']['against'][$p]++;
		}
	}

# 	tiles travelled
# --------------------------------------------------------------------------

	public function tiles_travelled_whilst_hold($player, $time) {
		$tiles = $this->getTilesTravelled($player, $time);

		$this->tiles_travelled_whilst_hold['player'][$player] += $tiles;

		foreach($this->active as $p => $pt)
			if($pt == $this->active[$player])
				$this->tiles_travelled_whilst_hold['team']['for'][$p] += $tiles;
			else
				$this->tiles_travelled_whilst_hold['team']['against'][$p] += $tiles;
	}

#	power ups
# --------------------------------------------------------------------------

	public function pup($player, $time, $pup) {
		$this->pup['player'][$player][$pup]++;

		foreach($this->active as $p => $pt)
			if($pt == $this->active[$player])
				$this->pup['team']['for'][$p][$pup]++;
			else
				$this->pup['team']['against'][$p][$pup]++;

		$this->pup_start($player, $time, $pup);
	}

	public function pup_start($player, $time, $pup) {
		if(!isset($this->tmp['pup_start'][$player][$pup])) // if pup is already active dont reset time
			$this->tmp['pup_start'][$player][$pup] = $time;


			$this->tmp['pup_active']['player'][$player] = true;
			$teamID = $this->getTeam($player);
			$this->tmp['pup_active']['team'][$teamID] = true;
	}

	public function pup_stop($player, $time, $pup) {
		$this->pup_minutes['player'][$player][$pup] += ($time - $this->tmp['pup_start'][$player][$pup]);

		foreach($this->active as $p => $pt)
			if($pt == $this->active[$player])
				$this->pup_minutes['team']['for'][$p][$pup] += ($time - $this->tmp['pup_start'][$player][$pup]);
			else
				$this->pup_minutes['team']['against'][$p][$pup] += ($time - $this->tmp['pup_start'][$player][$pup]);

		unset($this->tmp['pup_start'][$player][$pup]);
		unset($this->tmp['pup_active']['player'][$player]);

		// check another player on team doesn't have an active pup
		$teamID = $this->getTeam($player);
		$teamPupActive = false;
		foreach($this->tmp['pup_active']['player'] as $_p => $_time)
			if($teamID === $this->getTeam($_p))
				$teamPupActive = true;

		if(!$teamPupActive)
			unset($this->tmp['pup_active']['team'][$teamID]);

		$this->tmp['pup_expired']['player'][$player] = $time;
		$this->tmp['pup_expired']['team'][$teamID] = $time;
	}

#	prevent
# --------------------------------------------------------------------------

	public function prevent($player, $time) {
		$this->prevent_start($player, $time);
	}

	public function prevent_start($player, $time) {
		// with eu 1414537 we have Dyballa stops preventing and stops playing at the same time. This breaks the script, so we need to determin who has an active prevent.
		$this->tmp['prevent_start_active'][$player] = true;

		$this->tmp['prevent_start'][$player] = $time;

		$this->prevent_whilst_team_hold_start_time($player, $time);
		$this->trigger_hold_whilst_team_prevent_start($player, $time);
	}

	public function prevent_stop($player, $time) {

		$this->prevent_minutes['player'][$player] += ($time - $this->tmp['prevent_start'][$player]);

		foreach($this->active as $p => $pt)
			if($pt == $this->active[$player])
				$this->prevent_minutes['team']['for'][$p] += ($time - $this->tmp['prevent_start'][$player]);
			else
				$this->prevent_minutes['team']['against'][$p] += ($time - $this->tmp['prevent_start'][$player]);

		$this->reset($player, $time);

		$this->tmp['prevent_stop_time'][$player] = $time;
		unset($this->tmp['prevent_start'][$player]);

		$this->trigger_hold_whilst_team_prevent_stop($player, $time);
		$this->prevent_whilst_team_hold_stop_time($player, $time);

		unset($this->tmp['prevent_start_active'][$player]);
	}

#	reset
# --------------------------------------------------------------------------
	public function reset($player, $time) {

		$team = $this->getTeam($player);
		$other_team = ($team == '1') ? 2 : 1;

		foreach($this->tmp['hold'][$other_team] as $_p => $arr)
			if($arr['start'] < $arr['stop'] && isset($arr['returned_by']))
				if($arr['stop'] < $this->tmp['prevent_start'][$player]) {

					$seconds_between_team_hold_stop_and_prevent_start = $this->seconds($this->tmp['prevent_start'][$player] - $arr['stop']);
					$hold_seconds = $this->seconds($arr['stop'] - $arr['start']);
					$prevent_seconds = $this->seconds($time - $this->tmp['prevent_start'][$player]);

					if($seconds_between_team_hold_stop_and_prevent_start < 2 && $prevent_seconds > 4)
						if(!$this->tilesAwayFromCapping($_p, $arr['stop'], 6) && $arr['returned_by'] != $player) {
							$this->reset_from_my_return['player'][$arr['returned_by']]++;
							$this->reset_from_my_prevent['player'][$player]++;

							foreach($this->active as $p => $pt)
								if($pt == $this->active[$player])
									$this->reset['team']['for'][$p]++;
								else
									$this->reset['team']['against'][$p]++;
						}
				}
	}

#	block
# --------------------------------------------------------------------------

	public function block($player, $time) {
		$this->block_start($player, $time);
	}

	public function block_start($player, $time) {
		$this->tmp['block_start'][$player] = $time;
		$this->tmp['block_start_time'][$player] = $time;

		// $this->block_whilst_team_hold_start_time($player, $time);
		$this->trigger_hold_whilst_team_block_start($player, $time);
	}

	public function block_stop($player, $time) {
		$this->block_minutes['player'][$player] += ($time - $this->tmp['block_start'][$player]);

		foreach($this->active as $p => $pt)
			if($pt == $this->active[$player])
				$this->block_minutes['team']['for'][$p] += ($time - $this->tmp['block_start'][$player]);
			else
				$this->block_minutes['team']['against'][$p] += ($time - $this->tmp['block_start'][$player]);

		$this->tmp['block_stop_time'][$player] = $time;
		unset($this->tmp['block_start'][$player]);

		$this->trigger_hold_whilst_team_block_stop($player, $time);
		// $this->block_whilst_team_hold_stop_time($player, $time);
	}

#	button
# --------------------------------------------------------------------------

	public function button($player, $time) {
		$this->button_start($player, $time);
	}

	public function button_start($player, $time) {
		$this->tmp['button_start'][$player] = $time;
	}

	public function button_stop($player, $time) {
		$this->button_minutes['player'][$player] += ($time - $this->tmp['button_start'][$player]);

		foreach($this->active as $p => $pt)
			if($pt == $this->active[$player])
				$this->button_minutes['team']['for'][$p] += ($time - $this->tmp['button_start'][$player]);
			else
				$this->button_minutes['team']['against'][$p] += ($time - $this->tmp['button_start'][$player]);

		unset($this->tmp['button_start'][$player]);
	}

#	kill
# --------------------------------------------------------------------------

	public function kill($player, $time) {
		foreach($this->tmp['time_start'] as $_player => $_pup)
			if($_player === $player)
				$this->time_stop($_player, $time);

		foreach($this->tmp['hold_start']['player'] as $_player => $_pup)
			if($_player === $player)
				$this->hold_stop($_player, $time);

		foreach($this->tmp['pup_start']['player'] as $_player => $_pup)
			if($_player === $player)
				$this->pup_stop($_player, $time, $_pup);

		foreach($this->tmp['prevent_start'] as $_player => $_pup)
			if($_player === $player)
				$this->prevent_stop($_player, $time);

		foreach($this->tmp['button_start'] as $_player => $_pup)
			if($_player === $player)
				$this->button_stop($_player, $time);

		foreach($this->tmp['block_start'] as $_player => $_pup)
			if($_player === $player)
				$this->block_stop($_player, $time);

		foreach($this->tmp['wtl_start']['player'] as $_player => $_pup)
			if($_player === $player)
				$this->wtl_stop($_player, $time);

		$this->return_streak_stop($player);
		$this->tag_streak_stop($player);

		$this->deactive($player);
	}

	public function kill_all($time) {
		foreach($this->tmp['time_start'] as $_player => $_val)
			$this->time_stop($_player, $time);

		foreach($this->tmp['hold_start'] as $_player => $_val) {
			$this->kept_flag($_player);
			$this->hold_stop($_player, $time, false, false);
		}

		foreach($this->tmp['pup_start']['player'] as $_player => $_pup)
			$this->pup_stop($_player, $time, $_pup);

		foreach($this->tmp['prevent_start'] as $_player => $_val)
			if(isset($this->tmp['prevent_start_active'][$player]))
				$this->prevent_stop($_player, $time);

		foreach($this->tmp['button_start'] as $_player => $_val)
			$this->button_stop($_player, $time);

		foreach($this->tmp['block_start'] as $_player => $_val)
			$this->block_stop($_player, $time);

		foreach($this->tmp['wtl_start']['player'] as $_player => $_pup)
			$this->wtl_stop($_player, $time);

		foreach($this->tmp['tag_streak'] as $player => $_streak)
			$this->tag_streak_stop($player);

		foreach($this->tmp['return_streak'] as $player => $_streak)
			$this->return_streak_stop($player);
	}

# 	kept flag
# --------------------------------------------------------------------------

	public function kept_flag($player) {
		$this->kept_flag['player'][$player]++;
	}

# 	score
# --------------------------------------------------------------------------

	public function score($player, $score) {
		$this->score['player'][$player] = $score;
	}

# 	flair
# --------------------------------------------------------------------------

	public function flair($player, $flair) {
		$this->flair['player'][$player] = $flair;
	}

# 	half result
# --------------------------------------------------------------------------

	public function result_half($player, $team, $team_data) {
		foreach($team_data as $team_id => $arr)
			# offset team id
			if(($team - 1) === $team_id)
				$team_caps = $arr['score'];
			else
				$opp_team_caps = $arr['score'];

		$win = ($team_caps > $opp_team_caps) ? 1: 0;
		$tie = ($team_caps === $opp_team_caps) ? 1 : 0;
		$lose = ($team_caps < $opp_team_caps) ? 1: 0;

		$this->result_half['player'][$player]['win'] = $win;
		$this->result_half['player'][$player]['tie'] = $tie;
		$this->result_half['player'][$player]['lose'] = $lose;
	}

# 	authenticated
# --------------------------------------------------------------------------

	public function authenticated($player, $authenticated) {
		$this->authenticated['player'][$player] = $authenticated;
	}

# 	degree
# --------------------------------------------------------------------------

	public function degree($player, $degree) {
		$this->degree['player'][$player] = $degree;
	}

# 	team
# --------------------------------------------------------------------------

	public function team($player, $team) {
		$this->team['player'][$player] = $team;
	}

# 	cap from regrab
# --------------------------------------------------------------------------

	public function cap_from_regrab($player, $time) {
		if($this->tmp['regrab_active'][$player]) {
			// prevent cap whilst opponents prevent
			unset($this->tmp['grab_whilst_opponents_prevent_active'][$player]);

			$regrabPlayer = $this->tmp['regrab_active'][$player];

			$this->cap_from_regrab['player'][$player]++;
			$this->cap_from_my_regrab['player'][$regrabPlayer]++;
			$this->tmp['assist'][$regrabPlayer] = true;

			foreach($this->active as $_p => $_pt)
				if($_pt == $this->active[$player])
					$this->cap_from_regrab['team']['for'][$_p]++;
				else
					$this->cap_from_regrab['team']['against'][$_p]++;
		}
	}

# 	cap from handoff
# --------------------------------------------------------------------------

	public function cap_from_handoff($player, $time) {
		if($this->tmp['handoff_active'][$player]) {
			// prevent cap whilst opponents prevent
			unset($this->tmp['grab_whilst_opponents_prevent_active'][$player]);

			$handoffPlayer = $this->tmp['handoff_active'][$player];

			$this->cap_from_handoff['player'][$player]++;
			$this->cap_from_my_handoff['player'][$handoffPlayer]++;
			$this->tmp['assist'][$handoffPlayer] = true;

			foreach($this->active as $_p => $_pt)
				if($_pt == $this->active[$handoffPlayer])
					$this->cap_from_handoff['team']['for'][$_p]++;
				else
					$this->cap_from_handoff['team']['against'][$_p]++;
		}
	}

# 	cap from prevent
# --------------------------------------------------------------------------

	public function cap_from_prevent($player, $time) {
		$this->cap_from_my_prevent($player, $time);

		$check_cap_from_prevent = false;
		foreach($this->tmp['prevent_start'] as $p => $ptime)
			if($this->active[$p] == $this->active[$player]) {
				$check_cap_from_prevent = true;
				$this->cap_from_prevent['player'][$player]++;

				foreach($this->active as $_p => $_pt)
					if($_pt == $this->active[$player])
						$this->cap_from_prevent['team']['for'][$_p]++;
					else
						$this->cap_from_prevent['team']['against'][$_p]++;
				break;
			}

		// check blocks that stopped 2 seconds prior to cap
		if(!$check_cap_from_prevent)
			foreach($this->tmp['prevent_stop_time'] as $p => $pt)
				if($this->active[$p] == $this->active[$player]) {
					$seconds_between_prevent_stop_and_cap = $this->seconds($time - $pt);
					if($seconds_between_prevent_stop_and_cap <= 2) {
						foreach($this->active as $_p => $_pt)
							if($_pt == $this->active[$player])
								$this->cap_from_prevent['team']['for'][$_p]++;
							else
								$this->cap_from_prevent['team']['against'][$_p]++;

						break;
					}
				}
	}

# 	caps from my prevent
# --------------------------------------------------------------------------

	public function cap_from_my_prevent($player, $time) {
		$list = [];
		foreach($this->tmp['prevent_start'] as $p => $ptime)
			if($this->active[$p] == $this->active[$player]) {
				if($p != $player) {
					$list[$p] = '1';
					$this->cap_from_my_prevent['player'][$p]++;
					$this->tmp['assist'][$p] = true;
				}
			}

		// check prevent that stopped 2 seconds prior to cap
		foreach($this->tmp['prevent_stop_time'] as $p => $pt) {
			if(!isset($list[$p]))
				if($this->active[$p] == $this->active[$player]) {
					if($p != $player) {
						$seconds_between_prevent_stop_and_cap = $this->seconds($time - $pt);
						if($seconds_between_prevent_stop_and_cap <= 2) {
							$this->cap_from_my_prevent['player'][$p]++;
							$this->tmp['assist'][$p] = true;
						}
					}
				}
		}
	}

# 	caps from block
# --------------------------------------------------------------------------

	public function cap_from_block($player, $time) {
		$this->cap_from_my_block($player, $time);

		$check_cap_from_block = false;
		foreach($this->tmp['block_start'] as $p => $ptime)
			if($this->active[$p] == $this->active[$player]) {
				$check_cap_from_block = true;
				$this->cap_from_block['player'][$player]++;

				foreach($this->active as $_p => $_pt)
					if($_pt == $this->active[$player])
						$this->cap_from_block['team']['for'][$_p]++;
					else
						$this->cap_from_block['team']['against'][$_p]++;
				break;
			}

		// check blocks that stopped 2 seconds prior to cap
		if(!$check_cap_from_block)
			foreach($this->tmp['block_stop_time'] as $p => $pt)
				if($this->active[$p] == $this->active[$player]) {
					$seconds_between_block_stop_and_cap = $this->seconds($time - $pt);
					if($seconds_between_block_stop_and_cap <= 2) {
						foreach($this->active as $_p => $_pt)
							if($_pt == $this->active[$player])
								$this->cap_from_block['team']['for'][$_p]++;
							else
								$this->cap_from_block['team']['against'][$_p]++;

						break;
					}
				}

	}

# 	caps from my block
# --------------------------------------------------------------------------

	public function cap_from_my_block($player, $time) {
		$list = [];
		foreach($this->tmp['block_start'] as $p => $ptime)
			if($this->active[$p] == $this->active[$player]) {

				$seconds_block = $this->seconds($time - $this->tmp['block_start'][$p]);
				if($seconds_block < 10)
					if(!isset($this->tmp['hold_start'][$p])) {
						$list[$p] = '1';
						$this->cap_from_my_block['player'][$p]++;
						// block is shit so stop assists from it
						//$this->tmp['assist'][$p] = true;
					}
			}

		// check block that stopped 2 seconds prior to cap
		foreach($this->tmp['block_stop_time'] as $p => $pt) {
			if(!isset($list[$p]))
				if($this->active[$p] == $this->active[$player]) {
					$seconds_between_block_stop_and_cap = $this->seconds($time - $pt);
					if($seconds_between_block_stop_and_cap <= 2) {

						if(!isset($this->tmp['hold_start'][$p])) {
							$this->cap_from_my_block['player'][$p]++;
							// block is shit so stop assists from it
							// $this->tmp['assist'][$p] = true;
						}

					}
				}
		}

	}

# 	prevent whilst team hold
# --------------------------------------------------------------------------

	public function trigger_prevent_whilst_team_hold_start($player, $time) {
		foreach($this->tmp['prevent_start'] as $p => $pt)
			if($this->getTeam($player) === $this->getTeam($p))
				$this->prevent_whilst_team_hold_start_time($p, $time);
	}

	public function trigger_prevent_whilst_team_hold_stop($player, $time) {
		foreach($this->tmp['prevent_start'] as $p => $pt)
			if($this->getTeam($player) === $this->getTeam($p))
				$this->prevent_whilst_team_hold_stop_time($p, $time);
	}

	public function prevent_whilst_team_hold_start_time($player, $time) {
		foreach($this->tmp['hold_start'] as $p => $pt)
			if($this->getTeam($player) === $this->getTeam($p))
				$this->tmp['prevent_whilst_team_hold'][$player] = $time;
	}

	public function prevent_whilst_team_hold_stop_time($player, $time) {
		foreach($this->tmp['hold_start'] as $p => $pt)
			if($this->getTeam($player) === $this->getTeam($p)) {
				$prevent_start_time = $this->tmp['prevent_whilst_team_hold'][$player];
				$this->prevent_whilst_team_hold['player'][$player] += $time - $prevent_start_time;
			}
	}

## 	cap from my active pup
# --------------------------------------------------------------------------

	public function cap_whilst_having_active_pup($player, $time) {
		$teamID = $this->getTeam($player);

		$expiredAgo = isset($this->tmp['pup_expired']['player'][$player]) ?  $this->seconds($time - $this->tmp['pup_expired']['player'][$player]) : 1000;
		$expiredAgoTeam = isset($this->tmp['pup_expired']['team'][$teamID]) ?  $this->seconds($time - $this->tmp['pup_expired']['teamID'][$teamID]) : 1000;


		if(isset($this->tmp['pup_active']['player'][$player]) || $expiredAgo < 5) {
			$this->cap_whilst_having_active_pup['player'][$player]++;
		}

		if(isset($this->tmp['pup_active']['team'][$teamID]) || $expiredAgoTeam < 5) {
			$this->cap_whilst_team_have_active_pup['player'][$player]++;
		}
	}

## 	assists
# --------------------------------------------------------------------------

	public function assist($p) {
		foreach($this->tmp['assist'] as $player => $v) {
			if($player != $p)
				$this->assist['player'][$player]++;
		}
		unset($this->tmp['assist']);
	}

## 	hold whilst opposing team do
# --------------------------------------------------------------------------

	public function hold_whilst_opponents_do_start($player, $time) {
		if($this->isOpponentTeamHolding($player)) {
			$this->tmp['hold_whilst_opponents_do'][$player] = $time;

			foreach($this->tmp['hold_start'] as $p => $t) {
				if($p !== $player)
					$this->tmp['hold_whilst_opponents_do'][$p] = $time;
			}
		}
	}

	public function hold_whilst_opponents_do_stop($player, $time) {
		if(isset($this->tmp['hold_whilst_opponents_do'])) {

			$hold_start_time = $this->tmp['hold_whilst_opponents_do'][$player];

			$this->hold_whilst_opponents_do['player'][$player] += $time - $hold_start_time;

			foreach($this->tmp['hold_start'] as $p => $t) {
				if($p !== $player)
					$this->hold_whilst_opponents_do['player'][$p] += $time - $hold_start_time;
			}

			foreach($this->active as $p => $pt)
				$this->hold_whilst_opponents_do['team']['for'][$p] += $time - $hold_start_time;

			unset($this->tmp['hold_whilst_opponents_do']);

		}
	}

## 	hold whilst opposing team do not
# --------------------------------------------------------------------------

	public function hold_whilst_opponents_dont_start($player, $time) {
		if($this->isFlagInBase($player))
			$this->tmp['hold_whilst_opponents_dont'][$player] = $time;
		else
			foreach($this->tmp['hold_start'] as $p => $pt)
				if($this->getTeam($p) != $this->getTeam($player))
					$this->hold_whilst_opponents_dont_stop($p, $time, true);
	}

	public function hold_whilst_opponents_dont_stop($player, $time, $both_hold = false) {
		if($this->isFlagInBase($player) || $both_hold) {
			$hold_start_time = $this->tmp['hold_whilst_opponents_dont'][$player];

			$this->hold_whilst_opponents_dont['player'][$player] += $time - $hold_start_time;

			foreach($this->active as $p => $pt)
				if($pt == $this->active[$player])
					$this->hold_whilst_opponents_dont['team']['for'][$p] += $time - $hold_start_time;
				else
					$this->hold_whilst_opponents_dont['team']['against'][$p] += $time - $hold_start_time;
			if($both_hold)
				unset($this->tmp['hold_whilst_opponents_dont']);
		}
		else
			foreach($this->tmp['hold_start'] as $p => $pt)
				if($this->getTeam($p) != $this->getTeam($player))
					$this->tmp['hold_whilst_opponents_dont'][$p] = $time;
	}


# 	hold whilst team prevent
# --------------------------------------------------------------------------

	public function trigger_hold_whilst_team_prevent_start($player, $time) {
		foreach($this->tmp['hold_start'] as $p => $pt)
			if($this->getTeam($player) === $this->getTeam($p))
				$this->hold_whilst_team_prevent_start_time($p, $time);
	}

	public function trigger_hold_whilst_team_prevent_stop($player, $time) {
		foreach($this->tmp['hold_start'] as $p => $pt)
			if($this->getTeam($player) === $this->getTeam($p))
				$this->hold_whilst_team_prevent_stop_time($p, $time);
	}

	public function hold_whilst_team_prevent_start_time($player, $time) {
		if($this->isTeamPreventing($player))
			$this->tmp['hold_whilst_team_prevent'][$player] = $time;
	}

	public function hold_whilst_team_prevent_stop_time($player, $time) {
		$isPreventing = $this->isTeamPreventing($player);

		if($isPreventing) {
			$hold_start_time = $this->tmp['hold_whilst_team_prevent'][$player];
			$this->hold_whilst_team_prevent['player'][$player] += $time - $hold_start_time;

			foreach($this->active as $p => $pt)
				if($pt == $this->active[$player])
					$this->hold_whilst_prevent['team']['for'][$p] += $time - $hold_start_time;
				else
					$this->hold_whilst_prevent['team']['against'][$p] += $time - $hold_start_time;
		}

	}

# 	hold whilst team block
# --------------------------------------------------------------------------

	public function trigger_hold_whilst_team_block_start($player, $time) {
		foreach($this->tmp['hold_start'] as $p => $pt)
			if($this->getTeam($player) === $this->getTeam($p))
				$this->hold_whilst_team_block_start_time($p, $time);
	}

	public function trigger_hold_whilst_team_block_stop($player, $time) {
		foreach($this->tmp['hold_start'] as $p => $pt)
			if($this->getTeam($player) === $this->getTeam($p))
				$this->hold_whilst_team_block_stop_time($p, $time);
	}

	public function hold_whilst_team_block_start_time($player, $time) {
		if($this->isTeamBlocking($player))
			$this->tmp['hold_whilst_team_block'][$player] = $time;
	}

	public function hold_whilst_team_block_stop_time($player, $time) {
		$isBlocking = $this->isTeamBlocking($player);

		if($isBlocking) {
			$hold_start_time = $this->tmp['hold_whilst_team_block'][$player];
			$this->hold_whilst_team_block['player'][$player] += $time - $hold_start_time;
		}

	}

# 	win tie loss positions
# --------------------------------------------------------------------------

	public function wtl_positions($player, $time) {

		$old = $this->getScore();
		$this->scoreboard($player);
		$new = $this->getScore();

		if($old[1] === $old[2]) {
			if($new[1] > $new[2]) {
				foreach($this->active as $p => $pt) {
					$this->wtl_stop($p, $time);
					if($pt == 1) {
						$this->wtl_positions['player'][$p]['w']++;
						$this->tmp['wtl_start']['player'][$p]['w'] = $time;
						$this->tmp['quit_position'][$p] = 'w';
					}
					else {
						$this->wtl_positions['player'][$p]['l']++;
						$this->tmp['wtl_start']['player'][$p]['l'] = $time;
						$this->tmp['quit_position'][$p] = 'l';
					}
				}
			}
			else if($new[1] < $new[2]) {
				foreach($this->active as $p => $pt) {
					$this->wtl_stop($p, $time);
					if($pt == 1) {
						$this->wtl_positions['player'][$p]['l']++;
						$this->tmp['wtl_start']['player'][$p]['l'] = $time;
						$this->tmp['quit_position'][$p] = 'l';
					}
					else {
						$this->wtl_positions['player'][$p]['w']++;
						$this->tmp['wtl_start']['player'][$p]['w'] = $time;
						$this->tmp['quit_position'][$p] = 'w';
					}
				}
			}
		}

		else if($new[1] === $new[2]) {
			foreach($this->active as $p => $pt) {
				$this->wtl_stop($p, $time);
				$this->wtl_positions['player'][$p]['t']++;
				$this->tmp['wtl_start']['player'][$p]['t'] = $time;
				$this->tmp['quit_position'][$p] = 't';
			}
		}
	}

	public function wtl_start($player, $time) {

		$score = $this->getScore();
		$team = $this->getTeam($player);

		if($score[1] === $score[2]) {
			if($this->tmp['wtl_last'][$player] != 't') {
				// set initial tie
				if($score[1] === 0 && $score[2] === 0) {
					$this->wtl_positions['player'][$player]['t']++;
				}
				// if($this->tmp['quit_position'][$player] != 't') {
				// 	$this->wtl_positions['player'][$player]['t']++;
				// }
				$this->tmp['wtl_last'][$player] = 't';
			}
			$this->tmp['wtl_start']['player'][$player]['t'] = $time;
		}
		else if(($team === 1 && $score[1] > $score[2]) || ($team === 2 && $score[2] > $score[1])) {
			if($this->tmp['wtl_last'][$player] != 'w') {
				// if($this->tmp['quit_position'][$player] != 'w') {
				// 	$this->wtl_positions['player'][$player]['w']++;
				// }
				$this->tmp['wtl_last'][$player] = 'w';
			}
			$this->tmp['wtl_start']['player'][$player]['w'] = $time;
		}
		else {
			if($this->tmp['wtl_last'][$player] != 'l') {
				// if($this->tmp['quit_position'][$player] != 'l') {
				// 	$this->wtl_positions['player'][$player]['l']++;
				// }
				$this->tmp['wtl_last'][$player] = 'l';
			}
			$this->tmp['wtl_start']['player'][$player]['l'] = $time;
		}
	}

	public function wtl_stop($player, $time) {
		foreach($this->tmp['wtl_start']['player'][$player] as $_p => $_t) {
			$this->wtl_minutes['player'][$player][$_p] += ($time - $this->tmp['wtl_start']['player'][$player][$_p]);
			unset($this->tmp['wtl_start']['player'][$player]);
		};
	}

# 	game
# --------------------------------------------------------------------------

	public function won($player) {
		$teamID = $this->getTeam($player);
		$opponentTeamID = ($teamID === 1) ? 2 : 1;
		return ($this->tmp['scoreboard'][$teamID] > $this->tmp['scoreboard'][$opponentTeamID]) ? 1 : 0;
	}

	public function lost($player) {
		$teamID = $this->getTeam($player);
		$opponentTeamID = ($teamID === 1) ? 2 : 1;
		return ($this->tmp['scoreboard'][$teamID] < $this->tmp['scoreboard'][$opponentTeamID]) ? 1 : 0;
	}

	public function tie($player) {
		$teamID = $this->getTeam($player);
		$opponentTeamID = ($teamID === 1) ? 2 : 1;
		return ($this->tmp['scoreboard'][$teamID] === $this->tmp['scoreboard'][$opponentTeamID]) ? 1 : 0;
	}


# 	utils
# --------------------------------------------------------------------------

	public function _sec($time) {
		return floor($time/ 3600) . ':' . str_pad(floor($time % 3600 / 60), 2, '0', STR_PAD_LEFT) . '.' . str_pad(round($time % 60 / 0.6), 2, '0', STR_PAD_LEFT);
	}

	public function seconds($time) {
		// return round(($time / 3600) * 60, 0);
		return (($time / 3600) * 60);
	}

	public function getTeam($player) {
		return $this->active[$player];
	}

	public function getScore() {
		return [
			'1' => $this->tmp['scoreboard'][1] | 0,
			'2' => $this->tmp['scoreboard'][2] | 0,
		];
	}

	public function isOpponentTeamPreventing($player) {
		$isPreventing = false;
		foreach($this->tmp['prevent_start'] as $p => $pt)
			if($this->getTeam($player) != $this->getTeam($p) && $player != $p)
				$isPreventing = true;

		return $isPreventing;
	}

	public function isTeamPreventing($player) {
		$isPreventing = false;
		foreach($this->tmp['prevent_start'] as $p => $pt)
			if($this->getTeam($player) === $this->getTeam($p) && $player != $p)
				$isPreventing = true;

		return $isPreventing;
	}

	public function isOpponentTeamHolding($player) {
		$isHolding = false;
		foreach($this->tmp['hold_start'] as $p => $pt)
			if($this->getTeam($player) != $this->getTeam($p) && $player != $p)
				$isHolding = true;

		return $isHolding;
	}

	public function isTeamHolding($player) {
		$isHolding = false;
		foreach($this->tmp['hold_start'] as $p => $pt)
			if($this->getTeam($player) === $this->getTeam($p) && $player != $p)
				$isHolding = true;

		return $isHolding;
	}

	public function isFlagInBase($player) {
		$isFlagInBase = true;
		foreach($this->tmp['hold_start'] as $p => $pt)
			if($this->getTeam($player) != $this->getTeam($p))
				$isFlagInBase = false;

		return $isFlagInBase;
	}

	public function isTeamBlocking($player) {
		$isBlocking = false;
		foreach($this->tmp['block_start'] as $p => $pt)
			if($this->getTeam($player) === $this->getTeam($p) && $player != $p)
				$isBlocking = true;

		return $isBlocking;
	}

	public function isButtoning($player) {
		return (isset($this->tmp['button_start'][$player])) ? true : false;
	}

	public function isSplatMyHalf($player, $time, $opponentDrop = false) {
		$teamID = $this->getTeam($player);
		$myBase = $this->getBaseCoordinates($teamID);

		$opponentTeamID = ($teamID === 1) ? 2 : 1;

		$opponentBase = $this->getBaseCoordinates($opponentTeamID);

		if($opponentDrop)
			$t = ($teamID === 1) ? 2 : 1;
		else
			$t = $teamID;;

		$splatLocation = $this->mapping['splats'][$t][$time];


		$myBaseCoords = ($myBase['y']) + ($myBase['x']);
		$opponentBaseCoords = ($opponentBase['y']) + ($opponentBase['x']);
		$splatCoords = $splatLocation['y'] + $splatLocation['x'];

		$closest = $this->getClosest($splatCoords, [$myBaseCoords, $opponentBaseCoords]);

		return ($closest === $myBaseCoords) ? true : false;
	}

	public function tilesAwayFromConceding($player, $time, $tilesAway) {
		$teamID = $this->getTeam($player);
		$opponentTeamID = ($teamID === 1) ? 2 : 1;
		$myBase = $this->getBaseCoordinates($opponentTeamID);
		$splatLocation = $this->mapping['splats'][$opponentTeamID][$time];

		if(
			$myBase['y'] >= ($splatLocation['y'] - $tilesAway)
			&&
			$myBase['y'] <= ($splatLocation['y'] + $tilesAway)
			&&
			$myBase['x'] >= ($splatLocation['x'] - $tilesAway)
			&&
			$myBase['x'] <= ($splatLocation['x'] + $tilesAway)
		)
			return true;
		else
			return false;
	}

	public function tilesAwayFromCapping($player, $time, $tilesAway) {
		$teamID = $this->getTeam($player);
		$myBase = $this->getBaseCoordinates($teamID);
		$splatLocation = $this->mapping['splats'][$teamID][$time];

		if(
			$myBase['y'] >= ($splatLocation['y'] - $tilesAway)
			&&
			$myBase['y'] <= ($splatLocation['y'] + $tilesAway)
			&&
			$myBase['x'] >= ($splatLocation['x'] - $tilesAway)
			&&
			$myBase['x'] <= ($splatLocation['x'] + $tilesAway)
		)
			return true;
		else
			return false;
	}

	public function getBaseCoordinates($teamID) {
		return $this->mapping['flags'][$teamID];
	}

	public function getTilesTravelled($player, $time) {
		$teamID = $this->getTeam($player);
		$myBase = $this->getBaseCoordinates($teamID);
		$splatLocation = $this->mapping['splats'][$teamID][$time];

		$y = abs($myBase['y'] - $splatLocation['y']);
		$x = abs($myBase['x'] - $splatLocation['x']);

		return $y + $x;
	}

#	get the closest number
# --------------------------------------------------------------------------

	public function getClosest($search, $arr, $closest = null) {
		foreach ($arr as $item)
			if ($closest === null || abs($search - $closest) > abs($item - $search))
				$closest = $item;

		return $closest;
	}

#	convert flair id to flair name
# --------------------------------------------------------------------------

	public function getFlair($flairID, $flair = false) {
		switch($flairID) {
			// line 1
			case 1:	$flair = 'boards.day'; break;
			case 2:	$flair = 'boards.week'; break;
			case 3: $flair = 'boards.month'; break;
			case 4: $flair = 'winRate.good'; break;
			case 5: $flair = 'winRate.awesome'; break;
			case 6: $flair = 'winRate.insane'; break;
			case 7:	$flair = 'special.mod';	break;
			case 8:	$flair = 'special.mtc';	break;
			// line 2
			case 17: $flair = 'special.helper';	break;
			case 18: $flair = 'special.supporter'; break;
			case 19: $flair = 'special.developer'; break;
			case 20: $flair = 'special.supporter2'; break;
			case 21: $flair = 'special.supporter3'; break;
			case 22: $flair = 'special.contest'; break;
			case 23: $flair = 'special.kongregate'; break;
			case 24: $flair = 'special.supporter4'; break;
			case 25: $flair = 'special.bitcoin'; break;
			// line 3
			case 33: $flair = 'event.birthday'; break;
			case 34: $flair = 'event.stPatricksDay'; break;
			case 35: $flair = 'event.aprilFoolsDay'; break;
			case 36: $flair = 'event.easter'; break;
			case 37: $flair = 'event.hacked'; break;
			case 38: $flair = 'event.halloween'; break;
			case 39: $flair = 'event.survivor'; break;
			case 40: $flair = 'event.birthday2'; break;
			case 41: $flair = 'event.platformer'; break;
			case 42: $flair = 'event.stPatricksDay2'; break;
			case 43: $flair = 'event.aprilFoolsDay2'; break;
			case 44: $flair = 'event.football';	break;
			case 45: $flair = 'event.soccerball'; break;
			// line 4
			case 49: $flair = 'event.easter2'; break;
			case 50: $flair = 'event.carrot'; break;
			case 51: $flair = 'event.lgbt'; break;
			case 52: $flair = 'event.halloween2'; break;
			case 53: $flair = 'event.survivor2'; break;
			case 54: $flair = 'event.dootdoot';	break;
			case 55: $flair = 'event.birthday3'; break;
			case 56: $flair = 'event.platformer2'; break;
			case 57: $flair = 'event.stPatricksDay3'; break;
			case 58: $flair = 'event.easter3_1'; break;
			case 59: $flair = 'event.easter3_2'; break;
			// line 5
			case 65: $flair = 'event.easter3_3'; break;
			case 66: $flair = 'event.halloween3'; break;
			case 67: $flair = 'event.brains'; break;
			case 68: $flair = 'event.survivor3'; break;
			case 69: $flair = 'event.candycane'; break;
			case 70: $flair = 'event.gingerbread'; break;
			case 71: $flair = 'event.santahat'; break;
			case 72: $flair = 'event.birthday4'; break;
			case 73: $flair = 'event.platformer3'; break;
			case 74: $flair = 'event.purplecarrot'; break;
			case 75: $flair = 'event.candycorn'; break;
			case 76: $flair = 'event.halloween4'; break;
			case 77: $flair = 'event.survivor4'; break;
			// line 6
			case 81: $flair = 'degree.bacon'; break;
			case 82: $flair = 'degree.moon'; break;
			case 83: $flair = 'degree.freezing'; break;
			case 84: $flair = 'degree.dolphin'; break;
			case 85: $flair = 'degree.alien'; break;
			case 86: $flair = 'degree.roadsign'; break;
			case 87: $flair = 'degree.peace'; break;
			case 88: $flair = 'degree.flux'; break;
			case 89: $flair = 'degree.microphone'; break;
			case 90: $flair = 'degree.boiling'; break;
			case 91: $flair = 'degree.boiling2'; break;
			// line 7
			case 97: $flair = 'degree.dalmatians'; break;
			case 98: $flair = 'degree.abc'; break;
			case 99: $flair = 'degree.love'; break;
			case 100: $flair = 'degree.pokemon'; break;
			case 101: $flair = 'degree.phi'; break;
			case 102: $flair = 'degree.uturn'; break;
			case 103: $flair = 'degreen.world'; break;
			case 104: $flair = 'degree.penguin'; break;
			case 105: $flair = 'degree.magma'; break;
			case 106: $flair = 'degree.plane'; break;
			case 107: $flair = 'degree.atomic'; break;
			// line 8
			case 113: $flair = 'degree.bowling'; break;
			case 114: $flair = 'degree.pi'; break;
			case 115: $flair = 'degree.boxing'; break;
			case 116: $flair = 'degree.pencil'; break;
			case 117: $flair = 'degree.baseball'; break;
			case 118: $flair = 'degree.tomato'; break;
			case 119: $flair = 'degree.lightning1'; break;
			case 120: $flair = 'degree.lightning2';	break;
			case 121: $flair = 'degree.bones'; break;
			case 122: $flair = 'degree.arcreactor'; break;
			// line 9
			case 129: $flair = 'event.birthday5'; break;
			case 130: $flair = 'event.coin'; break;
			case 131: $flair = 'event.block'; break;
			case 132: $flair = 'event.worldJoiner'; break;
			case 133: $flair = 'event.fall'; break;
			case 134: $flair = 'event.halloween5'; break;
			case 135: $flair = 'event.survivor5'; break;
			case 136: $flair = 'event.xmastree'; break;
			default: $flair = ''; break;
		}
		return $flair;
	}

}
