<?php

//  https://ru.investing.com/currencies/eur-usd
//  ~1,1457 покупка 1 евро
//  ~1,1461 продажа 1 евро


class Order {
    public bool $operation_type;      //  тип операции - 0-покупка(Buy) банка или 1-продажа(Sell) банка
    public string $client;            //  Заявитель
    public int $amount;               //  Объём валюты (Про то почему я храню деньги в int'ах можете почитать вот тут:
                                      //  1. https://stackoverflow.com/questions/3730019/why-not-use-double-or-float-to-represent-currency/3730040#3730040
                                      //  2. https://news.ycombinator.com/item?id=20575702
                                      //  3. или просто var_dump(0.1+0.2==0.3);
    public int $price;                //  Курс валют USD->EUR по
    public string $from_currency;     //  "USD"
    public string $to_currency;       //  "EUR"
    public static int $counter = 0;   //  Счётчик количества заказов

    public function __construct(
        bool $operation_type,
        string $client,
        float $amount,
        float $price,
        string $from_currency,
        string $to_currency
    ) {
        $this->operation_type = $operation_type;
        $this->client = $client;
        $this->amount = intval($amount*10000);
        $this->price = intval($price*10000);
        $this->from_currency = $from_currency;
        $this->to_currency = $to_currency;
        self::$counter++;
    }

    public function __destruct(){
        self::$counter--;
    }

    /**
     * @return int
     */
    public static function getCounter(): int
    {
        return self::$counter;
    }
}


class Transaction {
    public string $buyer;           // Кто купил=Кому продали валюту
    public string $seller;          // Кто продал валюту=У кого купили валюту
    public int $amount;             //  Объём валюты (сколько продал)
    public int $price;              //  Курс валют (по какой цене продал) (аск/бид)
    public string $from_currency;   //  "USD"
    public string $to_currency;     //  "EUR"
    private static int $counter = 0; //  Счётчик количества транзакций

    public function __construct(string $buyer, string $seller, float $amount, float $price, string $from_currency, string $to_currency) {
        $this->buyer = $buyer;
        $this->seller = $seller;
        $this->amount = $amount;
        $this->price = $price;
        $this->from_currency = $from_currency;
        $this->to_currency = $to_currency;
        self::$counter++;
    }

//    public function __destruct(){
//        self::$counter--;
//    }

    /**
     * @return int
     */
    public static function getCounter(): int
    {
        return self::$counter;
    }
}

class TaskedDataStructure {
    public array $db; //наше хранилище(альернатива базе данных по ТЗ)(массив)

    //добавление заявки на покупку или продажу
    public function pushOrder(Order $order) {
        if ($order->operation_type) {
            $this->db["asks"][] = (array) $order;
        } else {
            $this->db["bids"][] = (array) $order;
        }
        $this->setSpread((array)$this->db["asks"], (array)$this->db["bids"]);
    }

    public function setSpread(array $asks, array $bids)
    {
        foreach ($asks as $key => $value) {
            $min_ask = $this->db["spread"]["min_ask"];
            if (is_null($min_ask) || $min_ask > $value["price"]) {
                $this->db["spread"]["min_ask"] = $value["price"];
            }
        }
        foreach ($bids as $key => $value) {
            $max_bid = $this->db["spread"]["max_bid"];
            if (is_null($max_bid) || $max_bid < $value["price"]) {
                $this->db["spread"]["max_bid"] = $value["price"];
            }
        }
    }

    //разница между минимальной ценой продажи и максимальной ценой покупки
    public function getSpread()
    {
        $this->setSpread((array)$this->db["asks"], (array)$this->db["bids"]);
        $spread = array(
            "min_ask" => $this->db["spread"]["min_ask"],
            "max_bid" => $this->db["spread"]["max_bid"]
        );
        return $spread;
//        print_r($spread);
    }

    /**
     * @param array $db
     */
    public function setDb(array $array, $key): void
    {
        $this->db[$key] = $array;
    }

    /**
     * @return Transaction[]
     */
    public function process(): array
    {
        $asks = $this->db["asks"];
        $bids = $this->db["bids"];
        // Ascending sort, по price'у
        usort($asks, function ($item1, $item2) {
            return $item2['price'] <=> $item1['price'];
        });
        $this->setDb($asks, "asks");

        // Descending sort, по price'у
        usort($bids, function ($item1, $item2) {
            return $item2['price'] <=> $item1['price'];
        });
        $this->setDb($bids, "bids");

        $array = [];
        foreach ($bids as $key => $bid_order) {
            foreach ($asks as $key => $ask_order) {
                if ($ask_order["price"] <= $bid_order["price"] ) {
                    if ($ask_order["amount"] >= $bid_order["amount"] ) {
                        $transaction = new Transaction(
                            $bid_order["client"],
                            $ask_order["client"],
                            $bid_order["amount"],
                            $ask_order["price"],
                            $bid_order["from_currency"],
                            $bid_order["to_currency"]
                        );
                        if (isset($transaction)){
                            $ask_order["amount"] = $ask_order["amount"] - $bid_order["amount"];
                            unset($bid_order);
                            if ($ask_order["amount"] == 0) {
                                unset($ask_order);
                            }
                            $array[] = (array) $transaction;
                            break;
                        }
                    } elseif ($ask_order["amount"] < $bid_order["amount"]) {
                        $transaction = new Transaction(
                            $bid_order["client"],
                            $ask_order["client"],
                            $ask_order["amount"],
                            $ask_order["price"],
                            $bid_order["from_currency"],
                            $bid_order["to_currency"]
                        );
                        if (isset($transaction)){
                            $bid_order["amount"] = $bid_order["amount"] - $ask_order["amount"];
                            unset($ask_order);
                            if ($bid_order["amount"] == 0) {
                                unset($bid_order);
                            }
                            $array[] = (array) $transaction;
                            break;
                        }
                    }
                }
            }
        }
        $this->setSpread((array)$asks, (array)$bids);

        echo "Table of Transactions" . "\n";
        $this->printTableOfTransactions($array);
//
        echo "Table of Database" . "\n";
        $this->printTableOfDatabase($this->db);

//        var_dump($array);
        return $array;
        //                echo $bid_order["price"] . "\t\t" . $ask_order["price"] ."\n\n";
    }

    //Вывод на экран таблицы транзакции
    public function printTableOfTransactions(array $transactions)
    {
        $mask = "|   %s   | %8.8s | %10.10s | %10.10s | %10.10s | %5.5s | %5.5s |\n";
        printf($mask, '№', 'Sell/Buy', 'Client', "Amount", "Price", "From", "To");
        $i = Transaction::getCounter();
        foreach ($transactions as $key => $value) {
            printf($mask,
                $i--,
                $value["buyer"],
                $value["seller"],
                $value["amount"]/10000,
                $value["price"],
                $value["from_currency"],
                $value["to_currency"]
            );
        }
        echo "\n\n";
    }

    //Вывод на экран таблицы биржевого стакана
    public function printTableOfDatabase(array $db)
    {
        $mask = "|   %s   | %8.8s | %10.10s | %10.10s | %10.10s | %5.5s | %5.5s | x |\n";
        printf($mask, '№', 'Sell/Buy', 'Client', "Amount", "Price", "From", "To");

//        print_r($db["asks"]);

        foreach ($db["asks"] as $key => $ask_order) {
            printf($mask,
                $key,
                $ask_order["operation_type"],
                $ask_order["client"],
                $ask_order["amount"]/10000,
                $ask_order["price"],
                $ask_order["from_currency"],
                $ask_order["to_currency"]
            );
        }

        $spread = $this->getSpread();
        printf($mask, ' ', '', '', "Min Ask: ", $spread["min_ask"], "", "");
        printf($mask, ' ', '', '', "Max Bid: ", $spread["max_bid"], "", "");

        foreach ($db["bids"] as $key => $bid_order) {
            printf($mask,
                $key,
                $bid_order["operation_type"],
                $bid_order["client"],
                $bid_order["amount"]/10000,
                $bid_order["price"],
                $bid_order["from_currency"],
                $bid_order["to_currency"]
            );
        }
    }
}

$full_Book_Order_Depth = new TaskedDataStructure();
$min = 10;      // Min Объём евро
$max = 10000;   // Max Объём евро
//  ~1,1457 покупка 1 евро
//  ~1,1461 продажа 1 евро

//Заполняем биржевой стакан 20-ю заявками
for ($i = 1; $i < 11; $i++) {

    // 1-продажа-sell -- Аски -- Верхняя часть биржевого стакана
    // Продавцы с именами от Client10 до Client1
    $sell_order = new Order(
        1,                     // 1-продажа
        "Client".(11-$i),             // Кто продаёт
        mt_rand($min, $max),               // amount - Объём/Количество продаваемой валюты
        mt_rand(11453, 11469)/10000,  // +/-0.0008 к price ~1,1461, попытался смоделировать случайные/разные цены продажи в
                                           // большую сторону выгодную продавцам, так как это их территория
                                           // mt_rand() генерирует рандомные целые числа, которые нужно поделить на
                                           // 10 000 чтобы получить наш случайный курс валюты в заданном диапазоне
        "USD",                 // что продаём
        "EUR"                    // что приобретаем
    );

    // 0-покупка-buy -- Биды -- Нижняя часть биржевого стакана
    // Покупатели с именами от Client1 до Client10
    $buy_order = new Order(
        0,
        "Client".($i),
        mt_rand($min, $max),
        mt_rand(11449, 11465)/10000,  // +/-0.0008 к price ~1,1457 -- покупка
        "USD",
        "EUR"
    );

    $full_Book_Order_Depth->pushOrder($sell_order);
    $full_Book_Order_Depth->pushOrder($buy_order);
}

//$transaction = new Transaction("Bank", "Nurlan", 100, 1.13);
//$full_Book_Order_Depth->getSpread();
$full_Book_Order_Depth->process();
//$full_Book_Order_Depth->getSpread();
//var_dump($full_Book_Order_Depth);
//echo "Hello";