<?php

namespace Ruudk\Payment\StripeBundle\Plugin;

use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Plugin\AbstractPlugin;
use JMS\Payment\CoreBundle\Plugin\Exception\FinancialException;
use JMS\Payment\CoreBundle\Plugin\PluginInterface;
use Omnipay\Stripe\Gateway;
use Psr\Log\LoggerInterface;
use Ruudk\Payment\StripeBundle\Form\CheckoutType;

class CheckoutPlugin extends AbstractPlugin
{
    /**
     * @var \Omnipay\Stripe\Gateway
     */
    protected $gateway;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(Gateway $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    public function processes($name)
    {
        return $name === CheckoutType::class;
    }

    public function approveAndDeposit(FinancialTransactionInterface $transaction, $retry)
    {
        $parameters = $this->getPurchaseParameters($transaction);

        $response = $this->gateway->purchase($parameters)->send();

        if($this->logger) {
            $this->logger->info(json_encode($response->getRequest()->getData()));
            $this->logger->info(json_encode($response->getData()));
        }

        if($response->isSuccessful()) {
            $transaction->setReferenceNumber($response->getTransactionReference());

            $data = $response->getData();

            $transaction->setProcessedAmount($data['amount'] / 100);
            $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
            $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);

            if($this->logger) {
                $this->logger->info(sprintf(
                    'Payment is successful for transaction "%s".',
                    $response->getTransactionReference()
                ));
            }

            return;
        }

        if($this->logger) {
            $this->logger->info(sprintf(
                'Payment failed for transaction "%s" with message: %s.',
                $response->getTransactionReference(),
                $response->getMessage()
            ));
        }

        $data = $response->getData();
        switch($data['error']['type']) {
            case "api_error":
                $ex = new FinancialException($response->getMessage());
                $ex->addProperty('error', $data['error']);
                $ex->setFinancialTransaction($transaction);

                $transaction->setResponseCode('FAILED');
                $transaction->setReasonCode($response->getMessage());
                $transaction->setState(FinancialTransactionInterface::STATE_FAILED);

                break;

            case "card_error":
                $ex = new FinancialException($response->getMessage());
                $ex->addProperty('error', $data['error']);
                $ex->setFinancialTransaction($transaction);

                $transaction->setResponseCode('FAILED');
                $transaction->setReasonCode($response->getMessage());
                $transaction->setState(FinancialTransactionInterface::STATE_FAILED);

                break;

            default:
                $ex = new FinancialException($response->getMessage());
                $ex->addProperty('error', $data['error']);
                $ex->setFinancialTransaction($transaction);

                $transaction->setResponseCode('FAILED');
                $transaction->setReasonCode($response->getMessage());
                $transaction->setState(FinancialTransactionInterface::STATE_FAILED);

                break;
        }

        throw $ex;
    }

    /**
     * @param FinancialTransactionInterface $transaction
     * @return array
     */
    protected function getPurchaseParameters(FinancialTransactionInterface $transaction)
    {
        /**
         * @var \JMS\Payment\CoreBundle\Model\PaymentInterface $payment
         */
        $payment = $transaction->getPayment();

        /**
         * @var \JMS\Payment\CoreBundle\Model\PaymentInstructionInterface $paymentInstruction
         */
        $paymentInstruction = $payment->getPaymentInstruction();

        /**
         * @var \JMS\Payment\CoreBundle\Model\ExtendedDataInterface $data
         */
        $data = $transaction->getExtendedData();

        $transaction->setTrackingId($payment->getId());

        $parameters = array(
            'amount'      => $payment->getTargetAmount(),
            'currency'    => $paymentInstruction->getCurrency(),
            'description' => $data->get('description'),
            'token'       => $data->get('token'),
        );

        return $parameters;
    }
}
