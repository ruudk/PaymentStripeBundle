<?php

namespace Ruudk\Payment\StripeBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class RuudkPaymentStripeExtension extends Extension
{
    /**
     * @param array            $configs
     * @param ContainerBuilder $container
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        $container->setParameter('ruudk_payment_stripe.api_key', $config['api_key']);

        foreach($config['methods'] AS $method) {
            $this->addFormType($container, $method);
        }

        /**
         * When logging is disabled, remove logger and setLogger calls
         */
        if(false === $config['logger']) {
            $container->getDefinition('ruudk_payment_stripe.controller.notification')->removeMethodCall('setLogger');
            $container->getDefinition('ruudk_payment_stripe.plugin.credit_card')->removeMethodCall('setLogger');
            $container->removeDefinition('monolog.logger.ruudk_payment_stripe');
        }
    }

    protected function addFormType(ContainerBuilder $container, $method)
    {
        $stripeMethod = 'stripe_' . $method;

        $definition = new Definition();
        if($container->hasParameter(sprintf('ruudk_payment_stripe.form.%s_type.class', $method))) {
            $definition->setClass(sprintf('%%ruudk_payment_stripe.form.%s_type.class%%', $method));
        } else {
            $definition->setClass('%ruudk_payment_stripe.form.stripe_type.class%');
        }
        $definition->addArgument($stripeMethod);

        $definition->addTag('payment.method_form_type');
        $definition->addTag('form.type', array(
            'alias' => $stripeMethod
        ));

        $container->setDefinition(
            sprintf('ruudk_payment_stripe.form.%s_type', $method),
            $definition
        );
    }
}
