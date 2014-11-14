<?php

namespace CS\Models;

use CS\Models\Site\SiteRecord,
    CS\Models\User\UserRecord,
    CS\Models\Order\OrderRecord,
    CS\Models\Device\DeviceRecord,
    CS\Models\Product\ProductRecord,
    CS\Models\Order\Product\OrderProductRecord,
    CS\Models\Limitation\LimitationRecord,
    CS\Models\Order\Payment\OrderPaymentRecord,
    CS\Models\Order\Payment\Product\OrderPaymentProductRecord;

/**
 * Description of TestHelper
 *
 * @author root
 */
class TestHelper
{

    /**
     *
     * @var PDO 
     */
    public static $db;

    /**
     *
     * @var SiteRecord 
     */
    public static $site;

    /**
     *
     * @var UserRecord 
     */
    public static $user;

    /**
     *
     * @var OrderRecord 
     */
    public static $order;

    /**
     *
     * @var ProductRecord 
     */
    public static $product;

    /**
     *
     * @var DeviceRecord 
     */
    public static $device;
    
    /**
     *
     * @var OrderProductRecord
     */
    public static $orderProduct;

    /**
     *
     * @var type LimitationRecord
     */
    public static $limitation;

    /**
     *
     * @var OrderPaymentRecord
     */
    public static $orderPayment;

    /**
     *
     * @var OrderpaymentProductRecord
     */
    public static $orderPaymentProduct;

    public static function create($db)
    {
        self::$db = $db;
        self::createSite();
        self::createUser();
        self::createOrder();
        self::createLimitation();
        self::createProduct();
        self::createOrderPayment();
        self::createOrderProduct();
        self::createOrderPaymentProduct();
        self::createDevice();
    }

    private static function createSite()
    {
        self::$site = new SiteRecord(self::$db);
        self::$site->setName('site.com')
                ->save();
    }

    private static function createUser()
    {
        self::$user = new UserRecord(self::$db);
        self::$user->setSite(self::$site)
                ->save();
    }

    private static function createOrder()
    {
        self::$order = new OrderRecord(self::$db);
        self::$order->setSite(self::$site)
                ->setUser(self::$user)
                ->setStatus(OrderRecord::STATUS_CREATED)
                ->setPaymentMethod(OrderRecord::PAYMENT_METHOD_FASTSPRING)
                ->setReferenceNumber('referenceNumber')
                ->save();
    }

    private static function createProduct()
    {
        self::$product = new ProductRecord(self::$db);
        self::$product->setName('product')
                ->setSite(self::$site)
                ->setLimitation(self::$limitation)
                ->save();
    }

    private static function createOrderProduct()
    {
        self::$orderProduct = new OrderProductRecord(self::$db);
        self::$orderProduct->setOrder(self::$order)
                ->setProduct(self::$product)
                ->setStatus(OrderProductRecord::STATUS_ADDED)
                ->save();
    }

    private static function createOrderPayment()
    {
        self::$orderPayment = new OrderPaymentRecord(self::$db);
        self::$orderPayment->setOrder(self::$order)
                ->setType(OrderPaymentRecord::TYPE_SALE)
                ->save();
    }

    private static function createLimitation()
    {
        self::$limitation = new LimitationRecord(self::$db);
        self::$limitation->setName('name')
                ->setLifetime(10)
                ->save();
    }

    private static function createOrderPaymentProduct()
    {
        self::$orderPaymentProduct = new OrderPaymentProductRecord(self::$db);
        self::$orderPaymentProduct->setOrderPayment(TestHelper::$orderPayment)
                ->setOrderProduct(TestHelper::$orderProduct)
                ->save();
    }

    private static function createDevice()
    {
        self::$device = new DeviceRecord(self::$db);
        self::$device->setUser(self::$user)
                ->setName('name')
                ->setUniqueId('123123123')
                ->save();
    }

}
