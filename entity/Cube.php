<?php
class Cube extends WarlocKer 
{
    public function squares()
    {
        return $this->hasMany('Square');
    }
}
