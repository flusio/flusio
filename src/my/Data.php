<?php

namespace flusio\my;

use Minz\Response;

class Data
{
    public function importation($request)
    {
        $data = file_get_contents(\Minz\Configuration::$data_path . '/shaarli.html');
        $dom = \SpiderBits\Dom::fromText($data);
        $selecteds = $dom->select('//dt/a|//dd');
        foreach ($selecteds->nodes() as $selected) {
            var_dump($selected->textContent);
        }
        return Response::ok('my/data/importation.phtml');
    }
}
