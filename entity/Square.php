<?php
class Square extends WarlocKer 
{
    public function cube()
    {
        return $this->belongsTo('Cube');
    }
}
