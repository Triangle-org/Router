<?php

use Triangle\Router;

Router::any('/hello/{name}', function (Triangle\Request $request, string $name): Triangle\Response {
    return response("Привет, $name!");
});