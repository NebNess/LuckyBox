<?php
/**
 * @name LuckyBox
 * @author Labi39454
 * @main LuckyBox\LuckyBox
 * @version 1.0.0
 * @api 3.0.0
 * 
 */

namespace LuckyBox;

use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\Config;

/*TODO:
 * 1. 랜덤박스 깠을 때 나오는 메시지 종류(UI, 액션바, 메시지, 안 띄우기)
 * 2. 오피가 강조할 보상 선택해서 메시지나 액션바로 강조(아마 메시지)
 * 3. 디폴트 false 필요하냐?
 * 
 * */

class LuckyBox extends PluginBase implements Listener{
    
    public $api;
    public $cookie = [];
    public $over = [];
    public $mode = [];
    public $pre = "§l[!] ";
    
    public function onEnable(){
        
        @mkdir($this->getDataFolder());
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $this->getServer()->getCommandMap()->register("랜덤박스", new Luckycommand($this));
        $this->list = new Config($this->getDataFolder(). 'data.yml', Config::YAML, [ "LuckyBox" => [] ]);
        $this->data = $this->list->getAll();
        $this->save();
        
    }
    
    public function save(){
        
        $this->list->setAll($this->data);
        $this->list->save();
        
    }
    
    public function WithColor($str){
        
        if (substr($str, 1,1) !== "§"){
            $str = "§b{$str}";
        }
        
        return $str;
    }
    
    public function PostPosition($str){
        
        $post = $this->has_batchim($str) ? '§r을' : '§r를';
        $post = "{$str}{$post}";
        return $post;
        
    }
    
    public function has_batchim($str, $charset = 'UTF-8') {
        
        $str = mb_convert_encoding($str, 'UTF-16BE', $charset);
        $str = str_split(substr($str, strlen($str) - 2));
        $code_point = (ord($str[0]) * 256) + ord($str[1]);
        if ($code_point < 44032 || $code_point > 55203) return 0;
        return ($code_point - 44032) % 28;
        
    }
    
    public function MakeImplode($item){
        
        $arr = [];
        
        foreach ($item->jsonSerialize() as $key => $value){
            
            array_push($arr, "{$key}@{$value}");
            
        }
        
        $arr = implode("|", $arr);
        
        return $arr;
        
    }
    
    public function MakeExplode($string){
        
        $item = [];
        
        foreach (explode("|", $string) as $ItemData){
            
            $ItemData = explode("@", $ItemData);
            $item[$ItemData[0]] = $ItemData[1];
            
        }
        
        $item = Item::jsonDeserialize($item);
        return $item;
        
    }
    
    public function Touch(PlayerInteractEvent $event){
        
        $player = $event->getPlayer();
        $name = $player->getName();
        $inven = $player->getInventory();
        $hand = $inven->getItemInHand();
        
        if ($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
            
            return;
            
        }
        
        if (in_array($name, array_keys($this->mode))){
            
            $this->Return($player);
            $event->setCancelled();
            return;
            
        }
            
        $hand->setCount(1);
        
        $SerialItem = $this->MakeImplode($hand);
        
        if (in_array($SerialItem, array_keys($this->data["LuckyBox"]))){
            
            $event->setCancelled();
            
            if (count($this->data["LuckyBox"][$SerialItem]) == 0){
                
                $this->Default($player, $this->pre."열 수 없는 상자입니다.", FALSE);
                return;
                
            }
            
            if ($player->isSneaking()){
                
                $this->cookie[$name] = $SerialItem;
                
                $form = $this->api->createCustomForm(function (Player $player, $data = null){
                    
                    $result = $data;
                    
                    $result = $result[0];
                    
                    if ($result === null){
                        return true;
                    }
                    
                    if (!is_numeric($result)){
                        
                        $this->Default($player, $this->pre."숫자가 아닙니다.", FALSE);
                        return;
                        
                    }
                    
                    $result = floor($result);
                    
                    if ($result < 1){
                        
                        $result = 1;
                        
                    }
                    
                    if ($result > 32){
                        
                        $this->Default($player, $this->pre."랜덤박스는 최대 32개까지만 한번에 열 수 있습니다.", FALSE);
                        return;
                        
                    }
                    
                    $SerialItem = $this->cookie[$player->getName()];
                    
                    $count = 0;
                    
                    foreach ($player->getInventory()->all($this->MakeExplode($SerialItem)) as $item){
                        
                        $count = $count + $item->getCount();
                        
                    }
                    
                    if ($result > $count){
                        
                        $this->Default($player, $this->pre."인벤토리에 {$result}개의 랜덤박스가 없습니다.", FALSE);
                        return;
                        
                    }
                    
                    for ($i = 0; $i < $result; $i++) {
                        $this->GivingProcess($player, $SerialItem);
                    }
                    
                });
                    
                    $form->setTitle("LuckyBox");
                    
                    $form->addInput("사용할 랜덤박스의 개수");
                    
                    $form->sendToPlayer($player);
                    return $form;
                
            }else{
                
                $this->GivingProcess($player, $SerialItem);
                
            }
            
        }
        
    }
    
    public function Return(Player $player){
        
        $form = $this->api->createSimpleForm(function (Player $player, int $data = null){
            
            $result = $data;
            
            if ($result === null){
                return true;
            }
            
            switch($result){
                
                case 0:
                    
                    $this->ChangeLuckyBoxPercentage($player);
                    break;
                    
                case 1:
                    
                    $this->AddLuckyBox($player);
                    break;
                    
                case 2:
                    
                    $this->AddReward($player);
                    break;
                    
                case 3:
                    
                    $this->RemoveLuckyBox($player);
                    break;
                    
                case 4:
                    
                    $this->RemoveReward($player);
                    break;
                    
                case 5:
                    
                    if (in_array($player->getName(), array_keys($this->mode))){
                        
                        unset($this->mode[$player->getName()]);
                        $this->Default($player, $this->pre."랜덤박스 모드를 껐습니다.", FALSE);
                        
                    }else{
                        
                        $this->mode[$player->getName()] = null;
                        $this->Default($player, $this->pre."랜덤박스 모드를 켰습니다.", FALSE);
                        
                    }
                    
                    break;
                    
            }
        });
            
            $form->setTitle("LuckyBox");
            
            $form->addButton("§l§8랜덤박스 확률 설정");
            $form->addButton("§l§8랜덤박스 상자 추가");
            $form->addButton("§l§8랜덤박스 보상 추가");
            $form->addButton("§l§8랜덤박스 상자 제거");
            $form->addButton("§l§8랜덤박스 보상 제거");
            
            if (in_array($player->getName(), array_keys($this->mode))){
                $switch = "§0Off";
            }else{
                $switch = "§aOn";
            }
            
            $form->addButton("§l§8랜덤박스 모드 {$switch}");
            $form->sendToPlayer($player);
            return $form;
        
    }
    
    public function Default(Player $player, string $text = "", bool $return = TRUE){
        
        if ($text == ""){
            
            if ($return == FALSE){
                
                return;
                
            }
            
            $this->Return($player);
            return;
            
        }
        
        $this->cookie[$player->getName()] = $return;
        
        $form = $this->api->createSimpleForm(function (Player $player, int $data = null){
            
            $result = $data;
            
            if ($result === null){
                return true;
            }
            
            if ($this->cookie[$player->getName()] == TRUE){
                
                $this->Return($player);
                
            }
            
        });
            
            $form->setTitle("LuckyBox");
            $form->setContent($text);
            $form->addButton("확인");
            
            $form->sendToPlayer($player);
            return $form;
        
    }
    
    public function ChangeLuckyBoxPercentage(Player $player){
        
        $this->Overlap3($player, "change");
        
    }
    
    public function AddLuckyBox(Player $player){
        
        $hand = $player->getInventory()->getItemInHand();
        $item = $this->MakeImplode($hand);
        
        if (in_array($item, array_keys($this->data["LuckyBox"])) or $hand->getId() == Item::AIR){
            
            $this->Default($player, $this->pre."이미 존재하는 상자이거나 공기입니다.");
            return;
            
        }
            
        $form = $this->api->createSimpleForm(function (Player $player, int $data = null){
            
            $result = $data;
            
            if ($result === null){
                return true;
            }
            
            switch($result){
                
                case 0:
                    
                    $item = $player->getInventory()->getItemInHand();
                    $item->setCount(1);
                    $arr = [];
                    
                    foreach ($item->jsonSerialize() as $key => $value){
                        
                        array_push($arr, "{$key}@{$value}");
                        
                    }
                    
                    $this->data["LuckyBox"][implode("|", $arr)] = [];
                    $this->Default($player, $this->pre."정상적으로 처리했습니다.", FALSE);
                    $this->save();
                    break;
                    
            }
        });
            
            $form->setTitle("LuckyBox");
            $form->setContent("*손에 들고 있는 아이템을 랜덤박스 상자로 설정합니다.");
            $form->addButton("확인");
            $form->sendToPlayer($player);
            return $form;
        
    }
    
    public function AddReward(Player $player){
        
        if (count(array_keys($this->data["LuckyBox"])) == 0 or $player->getInventory()->getItemInHand()->getId() == Item::AIR){
            
            $this->Default($player, $this->pre."보상을 추가할 상자가 없거나 추가하려는 아이템이 공기입니다.");
            return;
            
        }
            
        $form = $this->api->createSimpleForm(function (Player $player, int $data = null){
            
            $result = $data;
            
            if ($result === null){
                return true;
            }
            
            $item = $player->getInventory()->getItemInHand();
            $item->setCount(1);
            
            $SerialItem = $this->MakeImplode($item);
            
            if (in_array($SerialItem, array_keys(array_values($this->data["LuckyBox"])[$result]))){
                
                $this->Default($player, $this->pre."이 상자에는 같은 이름의 아이템이 있습니다.");
                return;
                
            }
            
            $this->Overlap2($player, "Add", $result);
            
        });
            
            $this->Overlap($form, $player);
            
    }
    
    public function RemoveLuckyBox(Player $player){
        
        if (count(array_keys($this->data["LuckyBox"])) == 0){
            
            $this->Default($player, $this->pre."삭제할 상자가 없습니다.");
            return true;
                
        }
        
        $form = $this->api->createSimpleForm(function (Player $player, int $data = null){
            
            $result = $data;
            
            if ($result === null){
                return true;
            }
            
            unset($this->data["LuckyBox"][array_keys($this->data["LuckyBox"])[$result]]);
            $this->Default($player, $this->pre."정상적으로 처리했습니다.");
            $this->save();
            
        });
            
            $this->Overlap($form, $player);
        
    }
    
    public function RemoveReward(Player $player){
        
        $this->Overlap3($player, "remove");
        
    }
    
    private function Overlap($form, $player){
        
        $form->setTitle("LuckyBox");
        
        foreach (array_keys($this->data["LuckyBox"]) as $box){
            
            $item = $this->MakeExplode($box);
            $form->addButton("상자 : '{$item->getName()}'");
            
        }
        
        $form->sendToPlayer($player);
        return $form;
        
    }
    
    private function Overlap2(Player $player, string $type, int $cnum, $num = null){
        
        $this->cookie[$player->getName()] = "{$type}:{$cnum}:{$num}";
        
        $form = $this->api->createCustomForm(function (Player $player, $data = null){
            
            $result = $data;
            
            if ($result === null){
                return true;
            }
            
            $cookie = explode(":", $this->cookie[$player->getName()]);
            $CertainBox = array_values($this->data["LuckyBox"])[$cookie[1]];
            $CertainBoxSerial = array_keys($this->data["LuckyBox"])[$cookie[1]];
            $ItemInCertainBox = array_values($CertainBox);
            
            $var = 0;
            
            foreach ($result as $offset){
                
                if (!is_numeric($offset)){
                    
                    $this->Default($player, $this->pre."값이 숫자가 아닙니다.", TRUE);
                    return;
                    
                }else{
                    
                    if ($var !== 2){
                        
                        if ($offset < 1){
                            
                            $result[$var] = 1;
                            
                        }
                        
                        $result[$var] = ceil($result[$var]);
                        
                    }
                    
                }
                
                $var = $var + 1;
                
            }
            
            if ($result[0] > $result[1]){
                
                $this->Default($player, $this->pre."최솟값이 최댓값보다 큽니다.", TRUE);
                return;
                
            }
            
            $result[2] = round($result[2], 2);
            
            if ($result[2] == 0){
                
                $result[2] = 0.01;
                
            }
            
            $per = 0;
            
            foreach ($ItemInCertainBox as $Serial){
                
                $per = $per + explode(":", $Serial)[2];
                
            }
            
            $old = 0;
            
            if ($cookie[2] !== ""){
                
                $old = explode(":", $ItemInCertainBox[$cookie[2]])[2];
                
            }
            
            if ($per - $old + $result[2] >= 100){
                
                $result[2] = 100 - $per + $old;
                
            }
            
            $result = implode(":", $result);
            
            switch ($cookie[0]){
                
                case "Add":
                    
                    $item = $player->getInventory()->getItemInHand();
                    $item->setCount(1);
                    
                    $SerialItem = $this->MakeImplode($item);
                    
                    $this->data["LuckyBox"][$CertainBoxSerial][$SerialItem] = $result;
                    break;
                    
                case "change":
                    
                    $this->data["LuckyBox"][$CertainBoxSerial][array_keys($CertainBox)[$cookie[2]]] = $result;
                    break;
                
            }
            
            $this->save();
            
            $CertainBox = array_values($this->data["LuckyBox"])[$cookie[1]];
            $CertainBoxSerial = array_keys($this->data["LuckyBox"])[$cookie[1]];
            $ItemInCertainBox = array_values($CertainBox);
            
            $array = [];
            
            foreach ($ItemInCertainBox as $reward){
                
                array_push($array, explode(":", $reward)[2]);
                
            }
            
            sort($array);
            
            $array1 = [];
            
            foreach ($array as $percent){
                
                foreach ($CertainBox as $key1 => $value1){
                    
                    if (explode(":", $value1)[2] == $percent and !isset($array1[$key1])){
                        
                        $array1[$key1] = $value1;
                        
                    }
                    
                }
                
            }
            
            $this->data["LuckyBox"][$CertainBoxSerial] = $array1;
            $this->Default($player, $this->pre."정상적으로 처리했습니다.", FALSE);
            $this->save();
            
        });
            
            $form->setTitle("LuckyBox");
            
            $value = [ 1, 1, 1 ];
            
            if ($num !== null){
                
                $value = explode(":", array_values(array_values($this->data["LuckyBox"])[$cnum])[$num]);
                
            }
            
            $form->addInput($this->pre."보상 지급 시 최소 개수", $value[0], $value[0]);
            $form->addInput($this->pre."보상 지급 시 최대 개수", $value[1], $value[1]);
            $form->addInput($this->pre."보상 지급 확률", $value[2], $value[2]);
            
            $form->sendToPlayer($player);
            return $form;
        
    }
    
    private function Overlap3(Player $player, string $type){
        
        $this->over[$player->getName()] = $type;
        
        $form = $this->api->createSimpleForm(function (Player $player, int $data = null){
            
            $result = $data;
            
            if ($result === null){
                return true;
            }
            
            $this->cookie[$player->getName()] = $result;
            
            $form = $this->api->createSimpleForm(function (Player $player, int $data = null){
                
                $result = $data;
                
                if ($result === null){
                    return true;
                }
                
                switch ($this->over[$player->getName()]){
                    
                    case "change":
                        
                        $this->Overlap2($player, "change", $this->cookie[$player->getName()], $result);
                        break;
                    
                    case "remove":
                        
                        $CertainBoxSerial = array_keys($this->data["LuckyBox"])[$this->cookie[$player->getName()]];
                        unset($this->data["LuckyBox"][$CertainBoxSerial][array_keys($this->data["LuckyBox"][$CertainBoxSerial])[$result]]);
                        $this->Default($player, $this->pre."보상을 성공적으로 제거했습니다.");
                        $this->save();
                        break;
                }
                
            });
                
                $form->setTitle("LuckyBox");
                
                foreach (array_values($this->data["LuckyBox"])[$result] as $Serial => $value){
                    
                    $item = $this->MakeExplode($Serial);
                    $value = explode(":", $value);
                    $form->addButton("§l보상 : '{$item->getName()}§r§f'\n({$value[2]}%%확률, 최솟값: {$value[0]}, 최댓값: {$value[1]})");
                    
                }
                
                $form->sendToPlayer($player);
                return $form;
                
        });
            
            $this->Overlap($form, $player);
        
    }
    
    public function GivingProcess($player, $SerialItem){
        
        $inven = $player->getInventory();
        $hand = $inven->getItemInHand();
        
        $hand->setCount(1);
        
        if ($hand->getId() !== Item::AIR){
            
            $hand->setCount($inven->getItemInHand()->getCount() - 1);
            $inven->setItemInHand($hand);
            
        }else{
            
            $inven->removeItem($this->MakeExplode($SerialItem));
            
        }
        
        $rand = mt_rand(1,10000);
        
        $item = Item::get(Item::AIR);
        
        $com = 0;
        $per = 0;
        
        foreach ($this->data["LuckyBox"][$SerialItem] as $key1 => $value1){
            
            $value1 = explode(":", $value1);
            $per = $per + $value1[2];
            
            if ($com < $rand and $rand <= $com + $value1[2] * 100){
                
                $item = $this->MakeExplode($key1);
                $item->setCount(mt_rand($value1[0], $value1[1]));
                break;
                
            }
            
            $com = $com + $value1[2] * 100;
            
        }
        
        $inven->addItem($item);
        
        $handN = $this->PostPosition($this->WithColor($hand->getName()));
        $itemN = $this->PostPosition($this->WithColor($item->getName()));
        
        if ($item->getCount() !== 1){
            
            $itemN = $this->WithColor("{$item->getName()} {$item->getCount()}§r개를");
            
        }
        
        if ($item->getId() == Item::AIR){
            
            $value1[2] = 100 - $per;
            
        }
        
        $this->Default($player, "{$handN} 열어서 §c{$value1[2]}%%§r 확률로 {$itemN} 얻었습니다.", FALSE);
        
    }
    
}

class LuckyCommand extends Command{
    
    private $plugin;
    
    public function __construct(LuckyBox $plugin){
        
        parent::__construct("랜덤박스", "§r랜덤박스를 관리합니다.", "/랜덤박스", ["랜"]);
        
        $this->plugin = $plugin;
        $this->setPermission('op');
    }
    
    public function execute(CommandSender $player, string $label, array $args){
        
        if ($player->isOp()){
            
            $this->plugin->Default($player);
            
        }
        
    }
    
}