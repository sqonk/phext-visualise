<?php

class Window
{
    public function __construct(private Visualiser $parent, private string $title, 
        private int $width, private int $height)
    {
        
    }
}