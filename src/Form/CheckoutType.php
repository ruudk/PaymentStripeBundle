<?php

namespace Ruudk\Payment\StripeBundle\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class CheckoutType extends StripeType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('token', HiddenType::class, array(
            'required' => false
        ));
    }
}
