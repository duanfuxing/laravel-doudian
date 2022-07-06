<?php

namespace duan617\DouDian;

use duan617\DouDian\Api\AfterSale;
use duan617\DouDian\Api\Alliance;
use duan617\DouDian\Api\AntiSpam;
use duan617\DouDian\Api\Bats;
use duan617\DouDian\Api\Brand;
use duan617\DouDian\Api\BuyIn;
use duan617\DouDian\Api\Coupons;
use duan617\DouDian\Api\CrossBorder;
use duan617\DouDian\Api\DutyFree;
use duan617\DouDian\Api\FreightTemplate;
use duan617\DouDian\Api\Iop;
use duan617\DouDian\Api\Logistics;
use duan617\DouDian\Api\Material;
use duan617\DouDian\Api\Member;
use duan617\DouDian\Api\OpenCloud;
use duan617\DouDian\Api\Order;
use duan617\DouDian\Api\OrderCode;
use duan617\DouDian\Api\Product;
use duan617\DouDian\Api\Recycle;
use duan617\DouDian\Api\Security;
use duan617\DouDian\Api\Shop;
use duan617\DouDian\Api\Sms;
use duan617\DouDian\Api\Spu;
use duan617\DouDian\Api\Storage;
use duan617\DouDian\Api\SupplyChain;
use duan617\DouDian\Api\Token;
use duan617\DouDian\Api\Topup;
use duan617\DouDian\Api\WareHouse;
use duan617\DouDian\Api\Yunc;
use Exception;
use Illuminate\Support\Str;

/**
 * Class DouDian.
 *
 * @property AfterSale   $afterSale
 * @property Alliance    $alliance
 * @property AntiSpam    $antiSpam
 * @property Bats        $bats
 * @property Brand       $brand
 * @property BuyIn       $buyIn
 * @property Coupons     $coupons
 * @property CrossBorder $crossBorder
 * @property DutyFree    $dutyFree
 * @property FreightTemplate    $freightTemplate
 * @property Logistics   $logistics
 * @property Iop         $iop
 * @property Material    $material
 * @property Member      $member
 * @property OpenCloud   $openCloud
 * @property Order       $order
 * @property OrderCode   $orderCode
 * @property Product     $product
 * @property Recycle     $recycle
 * @property Security    $security
 * @property Shop        $shop
 * @property Sms         $sms
 * @property Storage     $storage
 * @property SupplyChain $supplyChain
 * @property Spu         $spu
 * @property Token       $token
 * @property Topup       $topup
 * @property WareHouse   $wareHouse
 * @property Yunc        $yunc
 */
class DouDian
{
    private $config;
    private $shop_id = null;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function __get($class)
    {
        $class = '\\duan617\\DouDian\\Api\\'.Str::ucfirst($class);
        if (! class_exists($class)) {
            throw new Exception($class.', Not found', 404);
        }

        return new $class($this->config, $this->shop_id);
    }

    /**
     * 设定店铺ID.
     *
     * @param int $shopId
     *
     * @return $this
     */
    public function setShopId(int $shopId): self
    {
        $this->shop_id = $shopId;

        return $this;
    }

    /**
     * 获取店铺ID.
     *
     * @return mixed|null
     */
    public function getShopId()
    {
        return $this->shop_id;
    }
}
