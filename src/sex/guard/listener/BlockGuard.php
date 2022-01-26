<?php namespace sex\guard\listener;


/**
 *  _    _       _                          _  ____
 * | |  | |_ __ (_)_    _____ _ ______ __ _| |/ ___\_ _______      __
 * | |  | | '_ \| | \  / / _ \ '_/ __// _' | | /   | '_/ _ \ \    / /
 * | |__| | | | | |\ \/ /  __/ | \__ \ (_) | | \___| ||  __/\ \/\/ /
 *  \____/|_| |_|_| \__/ \___|_| /___/\__,_|_|\____/_| \___/ \_/\_/
 *
 * @author sex_KAMAZ
 * @link   http://universalcrew.ru
 *
 */

use pocketmine\block\BlockLegacyIds;
use pocketmine\block\ItemFrame;
use pocketmine\block\utils\SignText;
use pocketmine\network\mcpe\protocol\ItemFrameDropItemPacket;
use sex\guard\Manager;
use sex\guard\event\flag\FlagIgnoreEvent;
use sex\guard\event\flag\FlagCheckByBlockEvent;
use sex\guard\event\flag\FlagCheckByPlayerEvent;

use pocketmine\player\Player;
use pocketmine\block\Block;
use pocketmine\math\Vector3;

use pocketmine\event\Listener;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\LeavesDecayEvent;
use pocketmine\event\server\DataPacketReceiveEvent;


/**
 * @todo good listener should listen only one event.
 */
class BlockGuard implements Listener
{
	/**
	 * @var Manager
	 */
	private $api;


	/**
	 * @param Manager $api
	 */
	function __construct( Manager $api )
	{
		$this->api = $api;
	}


	/**
	 *  _ _      _
	 * | (_)____| |_____ _ __   ___ _ __
	 * | | / __/   _/ _ \ '_ \ / _ \ '_/
	 * | | \__ \| ||  __/ | | |  __/ |
	 * |_|_|___/|___\___|_| |_|\___|_|
	 *
	 *
	 * @param SignChangeEvent $event
	 *
	 * @priority        HIGH
	 * @ignoreCancelled TRUE
	 */
	function onSign( SignChangeEvent $event )
	{
		if( $event->isCancelled() )
		{
			return;
		}

		$player = $event->getPlayer();
		$block  = $event->getBlock();
		
		if( $this->isFlagDenied($block, 'place', $player) )
		{
			$event->cancel();
			return;
		}

		$origSign = $event->getSign();

		$line = $origSign->getText()->getLines();
		$list = ['sell rg', 'rg sell', 'region sell', 'sell region'];

		if( !in_array($line[0], $list) or intval($line[1]) <= 0 )
		{
			return;
		}

		$api = $this->api;

		if( $api->getValue('allow_sell', 'config') === FALSE )
		{
			return;
		}
		
		$region = $api->getRegion($block->getPosition());

		if( !isset($region) )
		{
			return;
		}

		$rname = $region->getRegionName();

		if( strtolower($player->getName()) != $region->getOwner() and !$player->hasPermission('sexguard.all') )
		{
			$api->sendWarning($player, $api->getValue('player_not_owner'));
			return;
		}

		$sign = $api->sign->get($rname, 'жопа');

		if( $sign !== 'жопа' )
		{
			$pos = $sign['pos'];

			if( $pos[0] != $block->getPosition()->getX() or $pos[1] != $block->getPosition()->getY() or $pos[2] != $block->getPosition()->getZ() )
			{
				$api->sendWarning($player, $api->getValue('sell_exist'));
				return;
			}
		}
		
		$price = intval($line[1]);

		for( $i = 0; $i < 4; $i++ )
		{
			$text = str_replace('{region}', $rname, $api->getValue('sell_text_'.($i + 1)));
			$text = str_replace('{price}',  $price, $text);

			$origSign->setText(new SignText([$i, $text]));
		}
		
		$data = [
			'pos'   => [$block->getPosition()->getX(), $block->getPosition()->getY(), $block->getPosition()->getZ()],
			'level' => $block->getPosition()->getWorld()->getFolderName(),
			'price' => $price
		];

		$api->sign->set($rname, $data);
		$api->sign->save();
	}


	/**
	 * @internal break flag.
	 *
	 * @param    BlockBreakEvent $event
	 *
	 * @priority        NORMAL
	 * @ignoreCancelled FALSE
	 */
	function onBreak( BlockBreakEvent $event )
	{
		if( $event->isCancelled() )
		{
			return;
		}
		
		$player = $event->getPlayer();
		$block  = $event->getBlock();

		if( $this->isFlagDenied($block, 'break', $player) )
		{
			$event->cancel();
			return;
		}

		if( $block->getId() == BlockLegacyIds::CHEST and $this->isFlagDenied($block, 'chest', $player) )
		{
			$event->cancel();
			return;
		}

		if( $block->getId() != BlockLegacyIds::SIGN_POST and $block->getId() != BlockLegacyIds::WALL_SIGN )
		{
			return;
		}
		
		$api = $this->api;
		
		if( count($api->sign->getAll()) > 0 or $api->getValue('allow_sell', 'config') === TRUE )
		{
			foreach( $api->sign->getAll() as $name => $data )
			{
				$pos = new Vector3($data['pos'][0], $data['pos'][1], $data['pos'][2]);
				$lvl = $data['level'];
				
				if( $block->getPosition()->equals($pos) and $block->getPosition()->getWorld()->getFolderName() == $lvl )
				{
					$api->sign->remove($name);
					$api->sign->save();
				}
			}
		}
	}


	/**
	 * @internal place flag.
	 *
	 * @param    BlockPlaceEvent $event
	 *
	 * @priority        NORMAL
	 * @ignoreCancelled FALSE
	 */
	function onPlace( BlockPlaceEvent $event )
	{
		if( $event->isCancelled() )
		{
			return;
		}
		
		$player = $event->getPlayer();
		$block  = $event->getBlock();
		
		if( $this->isFlagDenied($block, 'place', $player) )
		{
			$event->cancel();
		}
	}


	/**
	 * @internal decay flag.
	 *
	 * @param    LeavesDecayEvent $event
	 *
	 * @priority        NORMAL
	 * @ignoreCancelled FALSE
	 */
	function onDecay( LeavesDecayEvent $event )
	{
		if( $event->isCancelled() )
		{
			return;
		}
		
		$block = $event->getBlock();
		
		if( $this->isFlagDenied($block, 'decay') )
		{
			$event->cancel();
		}
	}


	/**
	 * @internal break flag.
	 *
	 * @param    DataPacketReceiveEvent $event
	 *
	 * @priority        NORMAL
	 * @ignoreCancelled TRUE
	 */
	function onPacketRecieve( DataPacketReceiveEvent $event )
	{
		$pk = $event->getPacket();

		if( $pk instanceof ItemFrameDropItemPacket)
		{
			$player = $event->getOrigin()->getPlayer();
			$tile   = $player->getPosition()->getWorld()->getTile($pk->blockPosition);

			if( !($tile instanceof ItemFrame) )
			{
				return;
			}

			$block = $tile->getFramedItem()->getBlock();

			if( $block->getPosition() === null )
			{
				return;
			}

			if( $this->isFlagDenied($block, 'frame', $player) )
			{
				$event->cancel();
			}
		}
	}


	/**
	 * @param  Block  $block
	 * @param  string $flag
	 * @param  Player $player
	 *
	 * @return bool
	 */
	private function isFlagDenied( Block $block, string $flag, Player $player = NULL ): bool
	{
		$api = $this->api;

		if( isset($player) )
		{
			if( $player->hasPermission('sexguard.noflag') )
			{
				return FALSE;
			}
		}

		$region = $api->getRegion($block->getPosition());

		if( !isset($region) )
		{
			if( $api->getValue('safe_mode', 'config') === TRUE )
			{
				if( isset($player) )
				{
					if( $player->hasPermission('sexguard.all') )
					{
						return FALSE;
					}
					
					$api->sendWarning($player, $api->getValue('warn_safe_mode'));
				}
				
				return TRUE;
			}
			
			return FALSE;
		}

		if( $region->getFlagValue($flag) )
		{
			return FALSE;
		}

		$event = new FlagCheckByBlockEvent($api, $region, $flag, $block, $player);

		$event->call();

		if( $event->isCancelled() )
		{
			return $event->isMainEventCancelled();
		}

		if( isset($player) )
		{
			$val = $api->getGroupValue($player);
			
			if( in_array($flag, $val['ignored_flag']) )
			{
				if( !in_array($region->getRegionName(), $val['ignored_region']) )
				{
					$event = new FlagIgnoreEvent($api, $region, $flag, $player);

					$event->call();

					if( $event->isCancelled() )
					{
						return $event->isMainEventCancelled();
					}

					return FALSE;
				}
			}
		}
		
		if( !isset($player) )
		{
			return TRUE;
		}
		
		$nick = strtolower($player->getName());
		
		if( $nick != $region->getOwner() )
		{
			if( !in_array($nick, $region->getMemberList()) )
			{
				$event = new FlagCheckByPlayerEvent($api, $region, $flag, $player, $block);

				$event->call();

				if( $event->isCancelled() )
				{
					return $event->isMainEventCancelled();
				}

				if( $flag == 'break' )
				{
					$pos    = $player->getPosition()->subtract($block->getPosition()->getX(), $block->getPosition()->getY(), $block->getPosition()->getZ());
					$pos->y = abs($pos->y + 2);
					$pos    = $pos->divide(8);

					$player->setMotion($pos);
				}

				$api->sendWarning($player, $api->getValue('warn_flag_'.$flag));
				return TRUE;
			}
		}
		
		return FALSE;
	}
}