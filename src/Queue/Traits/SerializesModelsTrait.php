<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue\Traits;

use Two\Queue\Contracts\QueueableEntityInterface;
use Two\Database\ModelIdentifier;

use ReflectionClass;
use ReflectionProperty;


trait SerializesModelsTrait
{
    /**
     * Préparez l'instance pour la sérialisation.
     *
     * @return array
     */
    public function __sleep()
    {
        $properties = (new ReflectionClass($this))->getProperties();

        foreach ($properties as $property) {
            $property->setValue($this, $this->getSerializedPropertyValue(
                $this->getPropertyValue($property)
            ));
        }

        return array_map(function ($property) {
            return $property->getName();
        }, $properties);
    }

    /**
     * Restaurez le modèle après la sérialisation.
     *
     * @return void
     */
    public function __wakeup()
    {
        foreach ((new ReflectionClass($this))->getProperties() as $property) {
            $property->setValue($this, $this->getRestoredPropertyValue(
                $this->getPropertyValue($property)
            ));
        }
    }

    /**
     * Obtenez la valeur de la propriété préparée pour la sérialisation.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function getSerializedPropertyValue($value)
    {
        return ($value instanceof QueueableEntityInterface)
            ? new ModelIdentifier(get_class($value), $value->getQueueableId()) : $value;
    }

    /**
     * Obtenez la valeur de la propriété restaurée après la désérialisation.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function getRestoredPropertyValue($value)
    {
        return ($value instanceof ModelIdentifier)
            ? (new $value->class)->newQuery()->findOrFail($value->id)
            : $value;
    }

    /**
     * Obtenez la valeur de la propriété pour la propriété donnée.
     *
     * @param  \ReflectionProperty  $property
     * @return mixed
     */
    protected function getPropertyValue(ReflectionProperty $property)
    {
        $property->setAccessible(true);

        return $property->getValue($this);
    }
}
