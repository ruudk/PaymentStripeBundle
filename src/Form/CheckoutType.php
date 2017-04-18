<?php

namespace Ruudk\Payment\StripeBundle\Form;

use Symfony\Component\Form\FormBuilderInterface;

class CheckoutType extends StripeType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('token', HiddenType::class, array(
            'required' => false
        ));
    }
}
