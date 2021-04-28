<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class UserMultipleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
             ->add('multiple', TextareaType::class, array('label' => 'Liste d\'identifiants ou de mails', 'required' => false))
             ->getForm(); 

    }
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
                                        'data_class' =>  null
                                     ));
    }
    public function getName()
    {
        return 'usermultiple';
    }
}