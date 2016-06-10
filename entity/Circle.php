<?php
class Circle extends WarlocKer 
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
