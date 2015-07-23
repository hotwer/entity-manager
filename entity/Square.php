<?php 
class Square extends EntityManager 
{
    public function cube()
    {
        return $this->belongsTo('Cube');
    }
}
