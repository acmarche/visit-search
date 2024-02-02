<?php

namespace AcMarche\PivotSearch\Data;


class DocumentElastic
{
    public ?string $id;

    public ?string $name;

    public ?string $excerpt;

    public ?string $content;

    public array $tags = [];

    public ?string $date;

    public ?string $url;

    public ?string $image;
}
