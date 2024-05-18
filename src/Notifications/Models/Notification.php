<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Notifications\Models;

use Two\Database\ORM\Model as BaseModel;
use Two\Notifications\Models\NotificationCollection;


class Notification extends BaseModel
{
    /**
     * La table associée au modèle.
     *
     * @var string
     */
    protected $table = 'notifications';

    /**
     * La clé primaire du modèle.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Les attributs remplissables sur le modèle.
     *
     * @var array
     */
    protected $fillable = array(
        'uuid', 'type', 'notifiable_id', 'notifiable_type', 'data', 'read_at'
    );

    /**
     * Les attributs qui doivent être mutés en dates.
     *
     * @var array
     */
    protected $dates = array('read_at');


    /**
     * Obtenez l'entité notifiable à laquelle appartient la notification.
     */
    public function notifiable()
    {
        return $this->morphTo();
    }

    /**
     * Obtenez l'attribut de données.
     */
    public function getDataAttribute($value)
    {
        return json_decode($value, true);
    }

    /**
     * Définissez l'attribut de données.
     */
    public function setDataAttribute($value)
    {
        $this->attributes['data'] = json_encode($value);
    }

    /**
     * Marquez la notification comme lue.
     *
     * @return void
     */
    public function markAsRead()
    {
        if (is_null($this->read_at)) {
            $this->forceFill(array(
                'read_at' => $this->freshTimestamp()
            ));

            $this->save();
        }
    }

    /**
     * Déterminez si une notification a été lue.
     *
     * @return bool
     */
    public function read()
    {
        return $this->read_at !== null;
    }

    /**
     * Déterminez si une notification n’a pas été lue.
     *
     * @return bool
     */
    public function unread()
    {
        return $this->read_at === null;
    }

    /**
     * Créez une nouvelle instance de collecte de notifications de base de données.
     *
     * @param  array  $models
     * @return \Two\Models\Notifications\NotificationsCollection
     */
    public function newCollection(array $models = array())
    {
        return new NotificationCollection($models);
    }
}
