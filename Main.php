<?php

//const ASK_EUR = 0.89;  //  (1$ равен 0.89 евро (аск))
//const BID_EUR = 1.13;  //  (1евро равен 1,13$ (бид))

class Order {
    public bool $operation_type;      //  тип операции - 1-покупка(Buy) банка или 0-продажа(Sell) банка
    public string $client;            //  Заявитель
    public int $amount;               //  Объём валюты (Про то почему я храню деньги в int'ах можете почитать вот тут:
                                      //  1. https://stackoverflow.com/questions/3730019/why-not-use-double-or-float-to-represent-currency/3730040#3730040
                                      //  2. https://news.ycombinator.com/item?id=20575702
                                      //  3. или просто var_dump(0.1+0.2==0.3);
    public int $price;                //  Курс валют USD->EUR по гуглу (1$ равен 0.88655 евро)(1евро равен 1.12797$)(А также 1$ равен 10825.00 сум)
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
        $this->amount = intval($amount*$this->getNumberOfDigitsAfterDecimalPoint($amount)); // можно задавать объём валюты
        $this->price = intval($price*$this->getNumberOfDigitsAfterDecimalPoint($price));  // и курс с любым значением после десятичного делителя(","-для людей или "."-для PHP)
        $this->from_currency = $from_currency;
        $this->to_currency = $to_currency;
        self::$counter++;
    }

    public function __destruct(){
        self::$counter--;
    }

    // Да, там ниже, когда создаются объекты, можно сразу передавать целые числа, но я сделал так, чтобы можно было
    // например в продакшэне сразу обрабатывать вводимые данные непосредственно с клавиатуры.
    private function getNumberOfDigitsAfterDecimalPoint(float $float_number): int
    {
        if (is_float($float_number)) {
//            $number_of_decimals = strlen(substr(strrchr(strval(floatval($float_number)), "."), 1)); // можно и так xD
            $number_of_decimals = strcspn(strrev(strval(floatval($float_number))), '.');
            if ($number_of_decimals != 0) {
                return 10 ** $number_of_decimals;
            }
        } elseif (is_int($float_number)) {
            return 1;
        }
    }
}


//class Transaction {
//    public string $bank;          // Кто продал валюту, условно Банк, но не обязательно, мог быть и другой участник биржи.
//    public string $client;        // Кому продали валюту
//    public int $amount;           //  Объём валюты (сколько продал)
//    public int $price;            //  Курс валют (по какой цене продал) (аск/бид)
////    public string $from_currency;   //  "USD"
////    public string $to_currency;     //  "EUR"
//
//    public function __construct(string $bank, string $client, float $amount, float $price) {
//        $this->bank = $bank;
//        $this->client = $client;
//        $this->amount = $amount;
//        $this->price = $price;
//    }
//}

class TaskedDataStructure {
    private array $db; //наше хранилище(альернатива базе данных по ТЗ)(массив)

    //добавление заявки на покупку или продажу
    public function pushOrder(Order $order) {
        if ($order->operation_type) {
            $this->db["asks"][] = (array) $order;
            $key = count($this->db["asks"])-1;
            $this->db["asks"][$key]["multiplication"] = (($order->amount/100000)*($order->price/100000));
        } else {
            $this->db["bids"][] = (array) $order;
            $key = count($this->db["bids"])-1;
            $this->db["bids"][$key]["multiplication"] = (($order->amount/100000)*($order->price/100000));
        }

        foreach ((array) $this->db["asks"] as $key => $value) {
            $min_ask = $this->db["spread"]["min_ask"];
            if (is_null($min_ask) || $min_ask > $value["price"]) {
                $this->db["spread"]["min_ask"] = $value["price"];
            }
        }
//        $min_ask = min(array_values(self::$db["asks"]));
//        $max_bid = max(array_values(self::$db["bids"]));
//        self::$db["spread"]["min_ask"] = $min_ask;
//        self::$db["spread"]["max_bid"] = $max_bid;
    }

    //разница между минимальной ценой продажи и максимальной ценой покупки
    public function getSpread() {
        $spread = array(self::$db["spread"]["min_ask"], self::$db["spread"]["max_bid"]);
    }

    /**
     * @param Transaction[]
     * @return array
     */
    public function process($transaction): array
    {
        return array($transaction);
    }
}

$full_Book_Order_Depth = new TaskedDataStructure();
$min = 10;      // Min Объём $
$max = 10000;   // Max Объём $

//Заполняем биржевой стакан 20-ю заявками
for ($i = 1; $i < 2; $i++) {

    // 0-продажа-sell -- Аски -- Верхняя часть биржевого стакана
    // Продавцы с именами от Client10 до Client1
    $sell_order = new Order(
        0,                     // 0-продажа
        "Client".(11-$i),             // Кто продаёт
        mt_rand($min, $max),               // amount - Объём/Количество продаваемой валюты
        mt_rand(87655, 89655)/100000, // price 0.88655, попытался смоделировать случайные/разные цены покупки
                                           // mt_rand() генерирует рандомные целые числа, которые нужно поделить на
                                           // 100 000 чтобы получить наш случайный курс валюты в заданном диапазоне
        "USD",                 // что продаём
        "EUR"                    // что приобретаем(, если честно сам чуток запутываюсь что писать в price 0.88655евро или 1.12797$)
                                           // P.S. дошло всё таки, когда мы продаём 1000$, мы отдаём 1000$, мы получаем 886.55$, то есть идёт умножение 1000 на 0.88655,
                                           // а когда покупаем 1000$, мы отдаём 886.55евро, и получаем 1000$, то есть идёт умножение 1000 на 1.12797.
    );

    // 1-покупка-buy -- Биды -- Нижняя часть биржевого стакана
    // Покупатели с именами от Client1 до Client10
    $buy_order = new Order(
        1,
        "Client".($i),
        mt_rand($min, $max),
        mt_rand(111797, 113797)/10000, // те самые 1.12797
        "USD",
        "EUR"
    );

    $full_Book_Order_Depth->pushOrder($sell_order);
    $full_Book_Order_Depth->pushOrder($buy_order);
}

//$transaction = new Transaction("Bank", "Nurlan", 100, 1.13);
var_dump($full_Book_Order_Depth);
echo "Hello";