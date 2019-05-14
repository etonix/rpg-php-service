<?php
function StartDuel($targetPlayerId)
{
    $gameData = \Base::instance()->get('GameData');
    $output = array('error' => '');
    $player = GetPlayer();
    $playerId = $player->id;
    $playerBattleDb = new PlayerBattle();
    $playerBattleDb->erase(array(
        'playerId = ? AND battleResult = ? AND battleType = ?',
        $playerId,
        EBattleResult::None,
        EBattleType::Arena
    ));

    if (!DecreasePlayerStamina($playerId, 'ARENA', 1)) {
        $output['error'] = 'ERROR_NOT_ENOUGH_ARENA_STAMINA';
    } else {
        $session = md5($playerId + '_' + $targetPlayerId + '_' + time());
        $newData = new PlayerBattle();
        $newData->playerId = $playerId;
        $newData->targetPlayerId = $targetPlayerId;
        $newData->session = $session;
        $newData->save();
        
        $opponent = new Player();
        $opponent = $opponent->load($targetPlayerId);
        
        $staminaTable = $gameData['staminas']['ARENA'];
        $stamina = GetStamina($playerId, $staminaTable['id']);
        $output['stamina'] = $stamina;
        $output['session'] = $session;
        
        $opponentCharacters = [];
        $opponentCharacterIds = GetFormationCharacterIds($opponent->id, $opponent->selectedArenaFormation);
        $count = count($opponentCharacterIds);
        $playerItemDb = new PlayerItem();
        for ($i = 0; $i < $count; ++$i)
        {
            $characterId = $opponentCharacterIds[$i];
            $characterEntry = $playerItemDb->load(array(
                'id = ?',
                $characterId
            ));
            if ($characterEntry) {
                $opponentCharacters[] = $characterEntry;
            }
        }
        $output['opponentCharacters'] = $opponentCharacters;
    }
    echo json_encode($output);
}

function FinishDuel($session, $battleResult, $deadCharacters)
{
    $gameData = \Base::instance()->get('GameData');
    $output = array('error' => '');
    $player = GetPlayer();
    $playerId = $player->id;
    $playerBattleDb = new PlayerBattle();
    $playerBattle = $playerBattleDb->load(array(
        'playerId = ? AND session = ?',
        $playerId,
        $session
    ));

    if ($playerBattle) {
        $output['error'] = 'ERROR_INVALID_BATTLE_SESSION';
    } else {
        // Prepare results
        $rewardItems = array();
        $createItems = array();
        $updateItems = array();
        $deleteItemIds = array();
        $updateCurrencies = array();
        $rewardSoftCurrency = 0;
        $rewardHardCurrency = 0;
        $rating = 0;
        $updateScore = 0;
        $arenaScore = $player->arenaScore;
        $arenaRank = GetArenaRank($arenaScore);
        // Set battle session
        $playerBattle->battleResult = $battleResult;
        if ($battleResult == EBattleResult::Win)
        {
            $rating = 3 - $deadCharacters;
            if ($rating <= 0) {
                $rating = 1;
            }
        }
        $playerBattle->rating = $rating;
        $playerBattle->update();
        if ($battleResult == EBattleResult::Win)
        {
            $oldArenaLevel = CalculateArenaRankLevel($arenaScore);
            $highestArenaRank = $player->highestArenaRank;
            $highestArenaRankCurrentSeason = $player->highestArenaRankCurrentSeason;
            $updateScore = $gameData['arenaWinScoreIncrease'];
            $arenaScore += $gameData['arenaWinScoreIncrease'];
            $arenaLevel = CalculateArenaRankLevel($arenaScore);
            // Arena rank up, rewarding items
            if ($arenaRank && $arenaLevel > $oldArenaLevel && $highestArenaRankCurrentSeason < $arenaLevel)
            {
                // Update highest rank
                $player->highestArenaRankCurrentSeason = $arenaLevel;
                if ($highestArenaRank < $arenaLevel) {
                    $player->highestArenaRank = $arenaLevel;
                }
                    
                // Soft currency
                $rewardSoftCurrency = $arenaRank['rewardSoftCurrency'];
                $softCurrency = GetCurrency($playerId, $gameData['currencies']['SOFT_CURRENCY']);
                $softCurrency->amount += $rewardSoftCurrency;
                $updateCurrencies[] = $softCurrency;
                // Hard currency
                $rewardHardCurrency = $arenaRank['rewardHardCurrency'];
                $hardCurrency = GetCurrency($playerId, $gameData['currencies']['HARD_CURRENCY']);
                $hardCurrency->amount += $rewardHardCurrency;
                $updateCurrencies[] = $hardCurrency;
                // Items
                $rewardItems = $arenaRank['rewardItems'];
                $countRewardItems = count($rewardItems);
                for ($i = 0; $i < $countRewardItems; ++$i) {
                    $rewardItem = $rewardItems[$i];
                    if (empty($rewardItem) || empty($rewardItem['id'])) {
                        continue;
                    }
                    
                    $addItemsResult = AddItems($playerId, $rewardItem['id'], $rewardItem['amount']);
                    if ($addItemsResult['success'])
                    {
                        $createItems = $addItemsResult['createItems'];
                        $updateItems = $addItemsResult['updateItems'];
                        $countCreateItems = count($createItems);
                        $countUpdateItems = count($updateItems);
                        for ($j = 0; $j < $countCreateItems; ++$j)
                        {
                            $createItem = $createItems[$j];
                            $createItem->save();
                            HelperUnlockItem($playerId, $createItem->dataId);
                            $rewardItems[] = $createItem;
                            $createItems[] = $createItem;
                        }
                        for ($j = 0; j < $countUpdateItems; ++$j)
                        {
                            $updateItem = $updateItems[$j];
                            $updateItem->update();
                            $rewardItems[] = $updateItem;
                            $updateItems[] = $updateItem;
                        }
                    }
                    // End add item condition
                }
                // End reward items loop
            }
        }
        else
        {
            $updateScore = -$gameData['arenaLoseScoreDecrease'];
            $arenaScore -= $gameData['arenaLoseScoreDecrease'];
            $player->arenaScore = $arenaScore;
        }
        $player->update();
        $output['rewardItems'] = $rewardItems;
        $output['createItems'] = $createItems;
        $output['updateItems'] = $updateItems;
        $output['deleteItemIds'] = $deleteItemIds;
        $output['updateCurrencies'] = $updateCurrencies;
        $output['rewardSoftCurrency'] = $rewardSoftCurrency;
        $output['rewardHardCurrency'] = $rewardHardCurrency;
        $output['rating'] = $rating;
        $output['updateScore'] = $updateScore;
        $output['player'] = $player;
    }
    echo json_encode($output);
}
?>