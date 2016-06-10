<?php
class Sphere extends WarlocKer
{
    public function circles()
    {
        return $this->hasMany('Circle');
    }
}
