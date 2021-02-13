This script takes a tagpro.eu ID and throws out some detailed statistics in csv format.

    require_once 'eu.reader.php';
    require_once 'eu.parser.php';
    require_once "eu.php";
    $eu = new eu;
    $eu->game('2489411');

It uses the tagpro.eu API, found at https://tagpro.eu/?science

The Tagpro.eu match .json file is saved to /matches/ so that we aren't spamming the server.

Point browser to /index.php?euids=2489411 to see how it in action, and use &headers=true to grab the headers.