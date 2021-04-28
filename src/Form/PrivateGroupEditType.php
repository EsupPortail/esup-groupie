<?php

namespace App\Form;

use App\Entity\Group;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use App\Form\PrivateMemberType;

class PrivateGroupEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
                
        $builder
                 ->add('members', CollectionType::class, array('entry_type' => PrivateMemberType::class)
                 );

    }
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
                                     'data_class' => Group::class,
                                     'attr' => ['id' => 'privategroupedit']
                                     ));
    }
    public function getName()
    {
        return 'privategroupedit';
    }
}

