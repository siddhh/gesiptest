<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mime\Address;

class TestNotificationType extends AbstractType
{
    private $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // Création liste balps
        $listeEmail = explode(',', $this->params->get('test_notification_balps'));
        $listeBalps = [];
        foreach ($listeEmail as $balp) {
            $address = Address::create(trim($balp));
            if (empty($address->getName())) {
                $address = new Address($balp, ucwords(str_replace('.', ' ', strstr($balp, '@', true))));
            }
            $listeBalps[$address->getName()] = $address->getAddress();
        }

        $builder
            ->add('listeBalps', ChoiceType::class, [
                'choices'  => $listeBalps,
                'label'    => 'BALP',
                'required' => true,
                'multiple' => true,
                'expanded' => true,
            ])
        ;
    }
}
