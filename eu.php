<?

class eu {

	public function game($euids) {
		$halves = explode(',', $euids);

		$half_num = 1;
		foreach($halves as $k => $half) {
			$raweuid = explode('+', $half);
			foreach($raweuid as $_k => $euid) {
				$data['euid'] = $euid;
				$data['half'] = $half_num;

				$raw = $this->match($data);

				$data['previous_score'] = [
					'team1' => $raw['previous_score'][1],
					'team2' => $raw['previous_score'][2],
				];
			}

			$half_num++;
		}
	}

	private function getMatchData($euid) {
		$file = "/root/tagpro/matches/$euid.json";

		if(file_exists($file))
			$match = file_get_contents($file);
		else {
			$download = 'https://tagpro.eu/data/?match=' . $euid;
			$match = file_get_contents($download);
			file_put_contents($file, $match);
		}

		return json_decode($match);
	}

	private function match($extras) {
		global $match, $player, $events, $splats;

		$store = new euParser();

		$events = [];

		$match = $this->getMatchData($extras['euid']);
		$data = arrayCastRecursive($match);

		$matchdata = [
			'euid' => $data['euid'],
			'map' => $data['map']['name'],
			'date' => $data['date'],
			'server' => $data['server'],
			'timelimit' => $data['timeLimit'],
			'duration' => $data['duration'],
			'redname' => $data['teams'][0]['name'],
			'redscore' => $data['teams'][0]['score'],
			'bluename' => $data['teams'][1]['name'],
			'bluescore' => $data['teams'][1]['score'],
		];

		$store->setScoreboard($extras['half'], $extras['previous_score']['team1'], $extras['previous_score']['team2']);

		$extras['map'] = $data['map']['name'];
		$extras['server'] = $data['server'];

		foreach($data['players'] as $p) {
			$store->score($p['name'], $p['score']);
			$store->flair($p['name'], $p['flair']);
			$store->authenticated($p['name'], $p['auth']);
			$store->degree($p['name'], $p['degree']);
			$store->team($p['name'], $p['team']);
			$store->result_half($p['name'], $p['team'], $data['teams']);
		}

		$pops = array(1 => array(), array());

		foreach($match->players as $player)
			new PlayerEventHandler();

		$store->mapping = $this->mapping($match);

		$events = $this->chronological($events);

		foreach($events as $time => $timeEvents) {
			foreach($timeEvents as $message) {
				$player = explode('## ', $message)[0];
				$msg = explode('## ', $message)[1];
				$t = timeFormat($time);

				$team = $this->team($msg);
				switch ($msg) {
					// start/join
					case 'starts in team 1':
					case 'joins team 1';
					case 'starts in team 2':
					case 'joins team 2':
						$store->active($player, $team, $time);
						break;
						// grab
					case 'grabs flag 1':
					case 'grabs flag 3':
						$store->grab($player, $time);
						break;
					// caps
					case 'captures flag 1':
					case 'captures flag 3':
						$store->cap($player, $time);
						break;
					// return
					case 'returns':
						$store->return($player, $time);
						break;
					// tags
					case 'tags':
						$store->tag($player, $time);
						break;
					// pop
					case 'pops':
						$store->pop($player, $time);
						break;
					// drops
					case 'drops flag 1':
					case 'drops flag 3':
						$store->drop($player, $time, $timeEvents);
							break;
					// prevent
					case 'starts preventing':
						$store->prevent($player, $time);
						break;
					case 'stops preventing':
						$store->prevent_stop($player, $time);
						break;
					// button
					case 'starts buttoning':
						$store->button($player, $time);
						break;
					case 'stops buttoning':
						$store->button_stop($player, $time);
						break;
					// block
					case 'starts blocking':
						$store->block($player, $time);
						break;
					case 'stops blocking':
						$store->block_stop($player, $time);
						break;
					// power ups
					case 'powers up 1':
					case 'powers up 2':
					case 'powers up 4':
						$pup = $this->pup($msg);
						$store->pup($player, $time, $pup);
						break;
					// power downs
					case 'powers down 1':
					case 'powers down 2':
					case 'powers down 4':
						$pup = $this->pup($msg);
						$store->pup_stop($player, $time, $pup);
						break;
					// end/quit
					case 'quits team 1':
					case 'quits team 2':
						$store->kill($player, $time);
						break;
					case 'ends in team 1':
					case 'ends in team 2':
						$store->kill_all($time);
						break;
				}
			}
		}

		$playerdata = $this->format($store, $extras);

		// foreach($playerdata as $id => $arr)
		// 	echo $arr['name'] .  ' --- assist: ' . $arr['assist'] . ' | cap_from_my_prevent: ' . $arr['cap_from_my_prevent'] . "\n";

		// print_r($playerdata);
		// die;

		if($_GET['headers'])
			$_GET['headers'] = $this->headers($playerdata);

		// grab the score and send to next half
		$matchdata['previous_score'] = $store->getScore();

		foreach($playerdata as $arr)
			echo implode(",", $arr) . "\n";

		return $matchdata;
	}

	// stupid fix for arrays not being in chronological order and fucking shit up
	function chronological($events) {
		foreach($events as $time => $timeEvents) {
			foreach($timeEvents as $key => $message) {
				$player = explode('## ', $message)[0];
				$msg = explode('## ', $message)[1];
				$t = timeFormat($time);

				if($msg === 'grabs flag 1') {
					foreach($timeEvents as $_key => $_message) {
						$_player = explode('## ', $_message)[0];
						$_msg = explode('## ', $_message)[1];
						$_t = timeFormat($_time);

						if($msg != $_msg) {
							if($_msg == 'drops flag 1') {
								unset($events[$time][$key]);
								$events[$time+1][0] = $player . '## grabs flag 1';
								continue;
							}
						}
					}
				}
			}
		}

		ksort($events);
		// error_log(print_r($events, true));

		return $events;
	}

	private function team($msg) {
		return (int)substr($msg, -1);
	}

	private function pup($msg) {
		switch((int)substr($msg, -1)) {
			case 1:
				$pup = 'jukejuice';
				break;
			case 2:
				$pup = 'rollingbomb';
				break;
			case 4:
				$pup = 'tagpro';
				break;
		}
		return $pup;
	}

	private function format($data, $extras) {
		foreach($data->score['player'] as $p => $arr) {

			$obj[] = [
                'euid' => $extras['euid'],
                'half' => $extras['half'],

                'name' => $this->fixPlayerName($p),
                'play_time' => $data->seconds($data->play_minutes['player'][$p]),

                'grab' => $data->grab['player'][$p] | 0,
                'grab_team_for' => $data->grab['team']['for'][$p] | 0,
                'grab_team_against' => $data->grab['team']['against'][$p] | 0,

                'grab_whilst_opponents_prevent' => $data->grab_whilst_opponents_prevent['player'][$p] | 0,
                'grab_whilst_opponents_prevent_team_for' => $data->grab_whilst_opponents_prevent['team']['for'][$p] | 0,
                'grab_whilst_opponents_prevent_team_against' => $data->grab_whilst_opponents_prevent['team']['against'][$p] | 0,
                'opponents_grab_whilst_my_prevent' => $data->opponents_grab_whilst_my_prevent['player'][$p] |0,

                'cap' => $data->cap['player'][$p] | 0,
                'cap_team_for' => $data->cap['team']['for'][$p] | 0,
                'cap_team_against' => $data->cap['team']['against'][$p] | 0,

                'hold' => $data->seconds($data->hold_minutes['player'][$p]) | 0,
                'hold_team_for' => $data->seconds($data->hold_minutes['team']['for'][$p]) | 0,
                'hold_team_against' => $data->seconds($data->hold_minutes['team']['against'][$p]) | 0,

                'prevent' => $data->seconds($data->prevent_minutes['player'][$p]) | 0,
                'prevent_team_for' => $data->seconds($data->prevent_minutes['team']['for'][$p]) | 0,
                'prevent_team_against' => $data->seconds($data->prevent_minutes['team']['against'][$p]) | 0,

                'block' => $data->seconds($data->block_minutes['player'][$p]) | 0,
                'block_team_for' => $data->seconds($data->block_minutes['team']['for'][$p]) | 0,
                'block_team_against' => $data->seconds($data->block_minutes['team']['against'][$p]) | 0,

                'button' => $data->seconds($data->button_minutes['player'][$p]) | 0,
                'button_team_for' => $data->seconds($data->button_minutes['team']['for'][$p]) | 0,
                'button_team_against' => $data->seconds($data->button_minutes['team']['against'][$p]) | 0,

                'drop' => $data->drop['player'][$p] | 0,
                'drop_team_for' => $data->drop['team']['for'][$p] | 0,
                'drop_team_against' => $data->drop['team']['against'][$p] | 0,
                'drop_within_my_half' => $data->drop_within_my_half['player'][$p] | 0,
                'drop_within_my_half_team_for' => $data->drop_within_my_half['team']['for'][$p] | 0,
                'drop_within_my_half_team_against' => $data->drop_within_my_half['team']['against'][$p] | 0,
                'drop_within_5_tiles_from_my_base' => $data->drop_within_5_tiles_from_my_base['player'][$p] | 0,
                'drop_within_2_tiles_from_my_base' => $data->drop_within_2_tiles_from_my_base['player'][$p] | 0,

                'return' => $data->return['player'][$p] | 0,
                'return_team_for' => $data->return['team']['for'][$p] | 0,
                'return_team_against' => $data->return['team']['against'][$p] | 0,
                'return_streak' => $data->return_streak['player'][$p] | 0,
                'return_from_button' => $data->return_from_button['player'][$p] | 0,
                'return_from_button_team_for' => $data->return_from_button['team']['for'][$p] | 0,
                'return_from_button_team_against' => $data->return_from_button['team']['against'][$p] | 0,
                'return_within_my_half' => $data->return_within_my_half['player'][$p] | 0,
                'return_within_my_half_team_for' => $data->return_within_my_half['team']['for'][$p] | 0,
                'return_within_my_half_team_against' => $data->return_within_my_half['team']['against'][$p] | 0,
                'return_from_button' => $data->return_from_button['player'][$p] | 0,
                'return_from_button_team_for' => $data->return_from_button['team']['for'][$p] | 0,
                'return_from_button_team_against' => $data->return_from_button['team']['against'][$p] | 0,
                'return_within_5_tiles_from_opponents_base' => $data->return_within_5_tiles_from_opponents_base['player'][$p] | 0,
                'return_within_5_tiles_from_opponents_base_team_for' => $data->return_within_5_tiles_from_opponents_base['team']['for'][$p] | 0,
                'return_within_5_tiles_from_opponents_base_team_against' => $data->return_within_5_tiles_from_opponents_base['team']['against'][$p] | 0,
                'return_within_2_tiles_from_opponents_base' => $data->return_within_2_tiles_from_opponents_base['player'][$p] | 0,
                'return_within_2_tiles_from_opponents_base_team_for' => $data->return_within_2_tiles_from_opponents_base['team']['for'][$p] | 0,
                'return_within_2_tiles_from_opponents_base_team_against' => $data->return_within_2_tiles_from_opponents_base['team']['against'][$p] | 0,
                'key_return' => $data->key_return['player'][$p] | 0,
                'key_return_team_for' => $data->key_return['team']['for'][$p] | 0,
                'key_return_team_against' => $data->key_return['team']['against'][$p] | 0,

                'quick_return' => $data->quick_return['player'][$p] | 0,
                'quick_return_team_for' => $data->quick_return['team']['for'][$p] | 0,
                'quick_return_team_against' => $data->quick_return['team']['against'][$p] | 0,

                'tag' => $data->tag['player'][$p] | 0,
                'tag_team_for' => $data->tag['team']['for'][$p] | 0,
                'tag_team_against' => $data->tag['team']['against'][$p] | 0,
                'tag_streak' => $data->tag_streak['player'][$p] | 0,
                'tag_within_my_half' => $data->tag_within_my_half['player'][$p] | 0,
                'tag_within_my_half_team_for' => $data->tag_within_my_half['team']['for'][$p] | 0,
                'tag_within_my_half_team_against' => $data->tag_within_my_half['team']['against'][$p] | 0,

                'pop' => $data->pop['player'][$p] | 0,
                'pop_team_for' => $data->pop['team']['for'][$p] | 0,
                'pop_team_against' => $data->pop['team']['against'][$p] | 0,
                'pop_within_my_half' => $data->pop_within_my_half['player'][$p] | 0,
                'pop_within_my_half_team_for' => $data->pop_within_my_half['team']['for'][$p] | 0,
                'pop_within_my_half_team_against' => $data->pop_within_my_half['team']['against'][$p] | 0,

                'kiss' => $data->kiss['player'][$p] | 0,
                'kiss_team' => $data->kiss['team']['for'][$p] | 0,
                'good_kiss' => $data->good_kiss['player'][$p] | 0,
                'good_kiss_team' => $data->good_kiss['team'][$p] | 0,
                'bad_kiss' => $data->bad_kiss['player'][$p] | 0,
                'bad_kiss_team' => $data->bad_kiss['team'][$p] | 0,

                'flaccid' => $data->flaccid['player'][$p] | 0,
                'flaccid_team_for' => $data->flaccid['team']['for'][$p] | 0,
                'flaccid_team_against' => $data->flaccid['team']['against'][$p] | 0,

                'long_hold' => $data->long_hold['player'][$p] | 0,
                'long_hold_team_for' => $data->long_hold['team']['for'][$p] | 0,
                'long_hold_team_against' => $data->long_hold['team']['against'][$p] | 0,
                'long_hold_and_cap' => $data->long_hold_and_cap['player'][$p] | 0,
                'long_hold_and_cap_team_for' => $data->long_hold_and_cap['team']['for'][$p] | 0,
                'long_hold_and_cap_team_against' => $data->long_hold_and_cap['team']['against'][$p] | 0,

                'super_hold' => $data->super_hold['player'][$p] | 0,
                'super_hold_team_for' => $data->super_hold['team']['for'][$p] | 0,
                'super_hold_team_against' => $data->super_hold['team']['against'][$p] | 0,
                'super_hold_and_cap' => $data->super_hold_and_cap['player'][$p] | 0,
                'super_hold_and_cap_team_for' => $data->super_hold_and_cap['team']['for'][$p] | 0,
                'super_hold_and_cap_team_against' => $data->super_hold_and_cap['team']['against'][$p] | 0,

                'regrab' => $data->regrab['player'][$p] | 0,
                'regrab_team_for' => $data->regrab['team']['for'][$p] | 0,
                'regrab_team_against' => $data->regrab['team']['against'][$p] | 0,

                'handoff' => $data->handoff['player'][$p] | 0,
                'handoff_team_for' => $data->handoff['team']['for'][$p] | 0,
                'handoff_team_against' => $data->handoff['team']['against'][$p] | 0,

                'good_handoff' => $data->good_handoff['player'][$p] | 0,
                'good_handoff_team_for' => $data->good_handoff['team']['for'][$p] | 0,
                'good_handoff_team_against' => $data->good_handoff['team']['against'][$p] | 0,

                'reset_from_my_return' => $data->reset_from_my_return['player'][$p] | 0,
                'reset_from_my_prevent' => $data->reset_from_my_prevent['player'][$p] | 0,
                'reset_team_for' => $data->reset['team']['for'][$p] | 0,
                'reset_team_against' => $data->reset['team']['against'][$p] | 0,

                'pup_rb' => $data->pup['player'][$p]['rollingbomb'] | 0,
                'pup_rb_time' => $data->seconds($data->pup_minutes['player'][$p]['rollingbomb']) | 0,
                'pup_rb_team_for' => $data->pup['team']['for'][$p]['rollingbomb'] | 0,
                'pup_rb_team_for_time' => $data->seconds($data->pup_minutes['team']['for'][$p]['rollingbomb']) | 0,
                'pup_rb_team_against' => $data->pup['team']['against'][$p]['rollingbomb'] | 0,
                'pup_rb_team_against_time' => $data->seconds($data->pup_minutes['team']['against'][$p]['rollingbomb']) | 0,

                'pup_jj' => $data->pup['player'][$p]['jukejuice'] | 0,
                'pup_jj_time' => $data->seconds($data->pup_minutes['player'][$p]['jukejuice']) | 0,
                'pup_jj_team_for' => $data->pup['team']['for'][$p]['jukejuice'] | 0,
                'pup_jj_team_for_time' => $data->seconds($data->pup_minutes['team']['for'][$p]['jukejuice']) | 0,
                'pup_jj_team_against' => $data->pup['team']['against'][$p]['jukejuice'] | 0,
                'pup_jj_team_against_time' => $data->seconds($data->pup_minutes['team']['against'][$p]['jukejuice']) | 0,

                'pup_tp' => $data->pup['player'][$p]['tagpro'] | 0,
                'pup_tp_time' => $data->seconds($data->pup_minutes['player'][$p]['tagpro']) | 0,
                'pup_tp_team_for' => $data->pup['team']['for'][$p]['tagpro'] | 0,
                'pup_tp_team_for_time' => $data->seconds($data->pup_minutes['team']['for'][$p]['tagpro']) | 0,
                'pup_tp_team_against' => $data->pup['team']['against'][$p]['tagpro'] | 0,
                'pup_tp_team_against_time' => $data->seconds($data->pup_minutes['team']['against'][$p]['tagpro']) | 0,

                'cap_from_prevent' => $data->cap_from_prevent['player'][$p] | 0,
                'cap_from_prevent_team_for' => $data->cap_from_prevent['team']['for'][$p] | 0,
                'cap_from_prevent_team_against' => $data->cap_from_prevent['team']['against'][$p] | 0,
                'cap_from_my_prevent' => $data->cap_from_my_prevent['player'][$p] | 0,

                'cap_from_block' => $data->cap_from_block['player'][$p] | 0,
                'cap_from_block_team_for' => $data->cap_from_block['team']['for'][$p] | 0,
                'cap_from_block_team_against' => $data->cap_from_block['team']['against'][$p] | 0,
                'cap_from_my_block' => $data->cap_from_my_block['player'][$p] | 0,

                'cap_from_regrab' => $data->cap_from_regrab['player'][$p] | 0,
                'cap_from_regrab_team_for' => $data->cap_from_regrab['team']['for'][$p] | 0,
                'cap_from_regrab_team_against' => $data->cap_from_regrab['team']['against'][$p] | 0,
                'cap_from_handoff' => $data->cap_from_handoff['player'][$p] | 0,
                'cap_from_my_handoff' => $data->cap_from_my_handoff['player'][$p] | 0,
                'cap_from_handoff_team_for' => $data->cap_from_handoff['team']['for'][$p] | 0,
                'cap_from_handoff_team_against' => $data->cap_from_handoff['team']['against'][$p] | 0,

                'cap_from_grab_whilst_opponents_prevent' => $data->cap_from_grab_whilst_opponents_prevent['player'][$p] | 0,
                'cap_from_grab_whilst_opponents_prevent_team_for' => $data->cap_from_grab_whilst_opponents_prevent['team']['for'][$p] | 0,
                'cap_from_grab_whilst_opponents_prevent_team_against' => $data->cap_from_grab_whilst_opponents_prevent['team']['against'][$p] | 0,

                'prevent_whilst_team_hold_time' => $data->seconds($data->prevent_whilst_team_hold['player'][$p]) | 0,
                'hold_whilst_team_prevent_time' => $data->seconds($data->hold_whilst_team_prevent['player'][$p]) | 0,
                'hold_whilst_prevent_team_for' => $data->seconds($data->hold_whilst_prevent['team']['for'][$p]) | 0,
                'hold_whilst_prevent_team_against' => $data->seconds($data->hold_whilst_prevent['team']['against'][$p]) | 0,

                'hold_whilst_opponents_dont' => $data->seconds($data->hold_whilst_opponents_dont['player'][$p]) | 0,
                'hold_whilst_opponents_dont_team_for' => $data->seconds($data->hold_whilst_opponents_dont['team']['for'][$p]) | 0,
                'hold_whilst_opponents_dont_team_against' => $data->seconds($data->hold_whilst_opponents_dont['team']['against'][$p]) | 0,

                'longest_hold' => $data->seconds($data->longest_hold['player'][$p]) | 0,

                'result_half_win' => $data->result_half['player'][$p]['win'],
                'result_half_tie' => $data->result_half['player'][$p]['tie'],
                'result_half_lose' => $data->result_half['player'][$p]['lose'],

                'position_win' => $data->wtl_positions['player'][$p]['w'] | 0,
                'position_win_time' => $data->seconds($data->wtl_minutes['player'][$p]['w']) | 0,
                'position_tie' => $data->wtl_positions['player'][$p]['t'] | 0,
                'position_tie_time' => $data->seconds($data->wtl_minutes['player'][$p]['t']) | 0,
                'position_loss' => $data->wtl_positions['player'][$p]['l'] | 0,
                'position_loss_time' => $data->seconds($data->wtl_minutes['player'][$p]['l']) | 0,

                'kept_flag' => $data->kept_flag['player'][$p] | 0,
                'score' => $data->score['player'][$p] | 0,
                'team' => $data->team['player'][$p],
                'flair' => $data->getFlair($data->flair['player'][$p]),
                'map' => $extras['map'],
                'server' => $extras['server'],

				'grab_whilst_opponents_hold' => $data->grab_whilst_opponents_hold['player'][$p] | 0,
				'grab_whilst_opponents_hold_team_for' => $data->grab_whilst_opponents_hold['team']['for'][$p] | 0,
				'grab_whilst_opponents_hold_team_against' => $data->grab_whilst_opponents_hold['team']['against'][$p] | 0,

				'grab_whilst_opponents_hold_long' => $data->grab_whilst_opponents_hold_long['player'][$p] | 0,
				'grab_whilst_opponents_hold_long_team_for' => $data->grab_whilst_opponents_hold_long['team']['for'][$p] | 0,
				'grab_whilst_opponents_hold_long_team_against' => $data->grab_whilst_opponents_hold_long['team']['against'][$p] | 0,

				'hold_whilst_opponents_do' => $data->seconds($data->hold_whilst_opponents_do['player'][$p]) | 0,
				'hold_whilst_opponents_do_team_for' => $data->seconds($data->hold_whilst_opponents_do['team']['for'][$p]) | 0,

				'assist' => $data->assist['player'][$p] | 0,

				'game_win' => ($extras['half'] === 2) ? $data->won($p) : 0,
				'game_tie' => ($extras['half'] === 2) ? $data->tie($p) : 0,
				'game_lost' => ($extras['half'] === 2) ? $data->lost($p) : 0,

				'cap_whilst_having_active_pup' => $data->cap_whilst_having_active_pup['player'][$p] | 0,
				'cap_whilst_team_have_active_pup' => $data->cap_whilst_team_have_active_pup['player'][$p] | 0,

				'flag_carry_distance' => $data->tiles_travelled_whilst_hold['player'][$p] | 0,
				'flag_carry_distance_team_for' => $data->tiles_travelled_whilst_hold['team']['for'][$p] | 0,
				'flag_carry_distance_team_against' => $data->tiles_travelled_whilst_hold['team']['against'][$p] | 0,

				'hold_before_cap' => $data->seconds($data->hold_before_cap['player'][$p]) | 0,

				'hold_whilst_team_block_time' => $data->seconds($data->hold_whilst_team_block['player'][$p]) | 0,

				'regrab_drop' => $data->regrab_drop['player'][$p] | 0,
				'regrab_pickup' => $data->regrab_pickup['player'][$p] | 0,

				'good_regrab_drop' => $data->good_regrab_drop['player'][$p] | 0,
				'good_regrab_pickup' => $data->good_regrab_pickup['player'][$p] | 0,

				'good_regrab_team_for' => $data->good_handoff['team']['for'][$p] | 0,
				'good_regrab_team_against' => $data->good_handoff['team']['against'][$p] | 0,

				'handoff_drop' => $data->handoff_drop['player'][$p] | 0,
				'handoff_pickup' => $data->handoff_pickup['player'][$p] | 0,

				'good_handoff_drop' => $data->good_handoff_drop['player'][$p] | 0,
				'good_handoff_pickup' => $data->good_handoff_pickup['player'][$p] | 0,

                'cap_from_my_regrab' => $data->cap_from_my_regrab['player'][$p] | 0,
			];
		}


		return $obj;
	}

	private function mapping($match) {
		global $mapHeight, $pops;

		$mapHeight = 1;

		$map = new MapEventHandler();

		ksort($pops);

		$splats = array();

		$i = 0;
		foreach($match->teams as $index => $team) {
			$i++;
			$p = $pops;
			ksort($p[$i]);
			$p[$i] = array_keys($p[$i]);
			$xypops[$i] = new SplatEventHandler($match, $mapHeight, $p, $team, $i);
			$splats[$i] = $xypops[$i]->splash;
		}

		return [
			'map' => [
				'width' => $match->map->width,
				'height' => $mapHeight,
			],
			'flags' => $map->flags,
			'splats' => $splats,
		];
	}

	public function headers($data) {
		return implode(',', array_keys($data[1]));
	}

	function fixPlayerName($player) {
		if($player === 'grimp') $player = 'GRIMP';
		if($player === 'mP') $player = 'MagicPigeon';
		if($player === 'MuCcy') $player = 'Muccy';
		if($player === 'MuCcY') $player = 'Muccy';
		if($player === 'Imp') $player = 'imp';
		if($player === 'IMP') $player = 'imp';
		if($player === '<>') $player = 'imp';

		return $player;
	}

}
