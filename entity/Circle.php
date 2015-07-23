<?php 
class Circle extends EntityManager 
{
    public function center()
    {
        return $this->hasOne('Center');
    }

    public function spheres()
    {
        return $this->belongsToMany('Sphere');
    }
}
