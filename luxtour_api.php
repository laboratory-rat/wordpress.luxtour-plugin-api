<?
/*
Plugin Name: Luxtour api
Description: api
Version: 0.1
Author: Oleg A. T.
Author URI: http://luxtour.online
Plugin URI: http://luxtour.online
*/

class luxtour_api
{

    public $db_tours = "luxtour_tours";
    public $db_hotels = "luxtour_hotels";
    public $db_hotel_to_tour = "luxtour_hotel_to_tour";
    public $db_apartments = "luxtour_apartments";
    public $db_orders = "luxtour_order";
    public $db_customers = "luxtour_customer";
	public $db_message = "luxtour_message";
	public $db_tel = "luxtour_tel";

    public $namespace = "luxtour_api/v1";

    function __construct()
    {
        add_action('admin_menu', array($this, 'plugin_menu'));
        add_action('rest_api_init', array($this, 'add_route'));

        register_activation_hook( __FILE__, array($this, 'activate'));
        register_deactivation_hook( __FILE__, array($this, 'deactivate'));
    }

    function activate()
    {

        global $wpdb;
        $database_name = $wpdb->prefix . $this->db_tours;
        $hotels_name = $wpdb->prefix . $this->db_hotels;
        $compare_name = $wpdb->prefix . $this->db_hotel_to_tour;

        if($wpdb->get_var("show tables like '$database_name'") != $database_name)
        {
            $sql = "CREATE TABLE `$database_name` (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                title varchar(45) NOT NULL,
                description varchar(255) NOT NULL);";

            $wpdb->query($sql);
        }

        if($wpdb->get_var("show tables like '$hotels_name'") != $hotels_name)
        {
            $sql = "CREATE TABLE `$hotels_name` (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                title varchar(45) NOT NULL,
                description varchar(255) NOT NULL,
                price_one INT NOT NULL);";

            $wpdb->query($sql);
        }

        if ($wpdb->get_var("show tables like '$compare_name'") != $compare_name)
        {
            $sql = "CREATE TABLE `$compare_name` (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                tour_id INT NOT NULL,
                hotel_id INT NOT NULL);";

            $wpdb->query($sql);
        }


        //exit();

    }

    function deactivate()
    {
        /*

        global $wpdb;
        $database_name = $wpdb->prefix . self::$db_name;

        if ($wpdb->get_var("show tables like '$database_name'") == $database_name)
        {
            $sql = "DROP TABLE " . $database_name . ";";
            $result = $wpdb->query($sql);
        }
        */
    }

    function add_route()
    {
        register_rest_route( 'luxtour_api/v1', '/xyu/', array(
            'methods' => 'GET',
            'callback' => array($this, 'xyu'),
        ));

        register_rest_route($this->namespace, '/news/(?P<count>[0-9]+)/(?P<start>[0-9]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_news'),
        ));

        register_rest_route('luxtour_api/v1', '/tours/', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_tours'),
        ));

        register_rest_route('luxtour_api/v1', '/hotels/(?P<id>[0-9]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_hotels'),
        ));

        register_rest_route( $this->namespace, '/add_order/', array(
            'methods'  => 'PUT',
            'callback' => array($this, 'AddOrder'),
        ));

        register_rest_route($this->namespace, '/statistics/(?P<type>[a-zA-Z]+)/(?P<count>[0-9]+)/(?P<id>[a-zA-Z0-9]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_statistics_all'),
        ));

        register_rest_route($this->namespace, '/get_stats/(?P<id>[a-zA-Z0-9]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_stats'),
        ));

		register_rest_route($this->namespace, '/post_message/', array(
			'methods' => 'PUT',
			'callback' => array($this, 'post_message'),
		));

		register_rest_route($this->namespace, '/tel/', array(
			'methods' => 'PUT',
			'callback' => array($this, 'tel'),
		));
    }

	function tel($data)
	{
		$data = json_decode($data);

		if (array_key_exists('fullname', $data) && array_key_exists('tel'))
		{
			$ip = $_SERVER['REMOTE_ADDR'];

			$fullname = htmlspecialchars($data['fullname']);
			$tel = htmlspecialchars($data['tel']);

			global $wpdb;
			$db = $wpdb->prefix.$this->db_tel;

			$sql = "select date from $db where ip = '$ip' order by date desc";
			$result = $wpdb->get_var($sql);

			if ($result != null)
			{
				$date1 = new DateTime(null, new DateTimeZone('Europe/Kiev'));
				$date2 = new DateTime($result);

				$interval = $date2->diff($date1);
			}

			if ($result == null || ($interval->h - 2) > 1 )
			{
				$sql = "insert into $db (`fullname`, `tel`, `ip`) values ('$fullname', '$tel', '$ip')";
				$wpdb->query($sql);
			}
		}
	}

	function post_message($data)
	{

		$data = json_decode($data->get_body(), true);

		if (array_key_exists('fullname', $data) && array_key_exists('email', $data) && array_key_exists('message', $data))
		{
			$ip = $_SERVER['REMOTE_ADDR'];

			$fullname = htmlspecialchars($data['fullname']);
			$email = htmlspecialchars($data['email']);
			$message = htmlspecialchars($data['message']);

			global $wpdb;
			$db = $wpdb->prefix.$this->db_message;

			$sql = "select date from $db where ip = '$ip' order by date desc";
			$result = $wpdb->get_var($sql);
			$interval = null;

			if ($result != null)
			{
				$datetime1 = new DateTime(null, new DateTimeZone('Europe/Kiev'));
				$datetime2 = new DateTime($result);
				$interval = $datetime2->diff($datetime1);
			}

			if($result == null || ($interval->h - 2) > 1)
			{
				$sql = "insert into $db (`fullname`, `email`, `message`, `ip`) values ('$fullname', '$email', '$message', '$ip')";
				$wpdb->query($sql);
			}
		}
	}

    function AddOrder($data)
    {
        $b = json_decode($data->get_body(), true);


        if (array_key_exists('tour', $b) && array_key_exists('hotel', $b) && array_key_exists('apartments', $b) &&
            array_key_exists('customers', $b) && array_key_exists('date_from', $b) && array_key_exists('date_until', $b) &&
            array_key_exists('agent_id', $b))
        {
            $tour_id = $b['tour'];
            $hotel_id = $b['hotel'];
            $apartments_id =$b['apartments'];

            $order_customers = $b['customers'];

            $date_from = $b['date_from'];
            $date_until = $b['date_until'];

            $key = $b['agent_id'];



            $comment = "";

            if (array_key_exists('comment', $b))
                $comment = $b['comment'];

            $customers_count = count($order_customers);



            global $wpdb;

            $orders = $wpdb->prefix.$this->db_orders;
            $customers = $wpdb->prefix.$this->db_customers;
            $apartments = $wpdb->prefix.$this->db_apartments;

			$sql_id = "select id from ololo_luxtour_agents where `key` = '$key'";
			$agent_id = $wpdb->get_var($sql_id);

            // days count

            $date_from=date("Y-m-d",strtotime($date_from));
            $date_until=date("Y-m-d",strtotime($date_until));

            $timeDiff = abs(strtotime($date_until) - strtotime($date_from));

            $numberDays = $timeDiff/86400;  // 86400 seconds in one day

            $numberDays = intval($numberDays) + 1;

            // dates to mysql format



            // Get total price

            $query_apartments_price = "select price from $apartments where id = $apartments_id";
            $apartments_price = $wpdb->get_var($query_apartments_price);

            $total_price = $apartments_price * $numberDays * $customers_count / 7;

            // insert new order;

            $query_order = "insert into $orders (agent_id, tour_id, hotel_id, apartments_id, customers_count, date_from, date_until, comment, total_price)".
            " values ($agent_id, $tour_id, $hotel_id, $apartments_id, $customers_count,".
            " cast('$date_from' as date), cast('$date_until' as date), '$comment', $total_price)";
            $wpdb->query($query_order);

            $order_id = $wpdb->insert_id;

            foreach ($order_customers as $key => $c)
            {
                $fullname = $c['fullname'];
                $sex = $c['sex'];

                $is_child = 0;
                if ($c['isChild'] == true)
                    $is_child = 1;

                $passport = $c['passport'];

                $inn = $c['inn'];

                $email = "";
                if (array_key_exists('email', $c))
                    $email = $c['email'];


                $tel = "";
                if (array_key_exists('tel', $c))
                    $tel = $c['tel'];

                $query_customer = "insert into $customers (order_id, fullname, sex, is_child, passport, inn, email, tel) values".
                " ($order_id, '$fullname', '$sex', $is_child, '$passport', '$inn', '$email', '$tel')";
                $wpdb->query($query_customer);
            }

            return $order_id;


        }

    }

    function get_tours()
    {
        global $wpdb;
        $tours = $wpdb->prefix . $this->db_tours;
        $hotels = $wpdb->prefix . $this->db_hotels;
        $compare = $wpdb->prefix . $this->db_hotel_to_tour;
        $apartments_db = $wpdb->prefix . $this->db_apartments;

        $sql = "select * from `$tours`";

        $output = $wpdb->get_results($sql, OBJECT_K);


        foreach($output as $row)
        {


            $compare_sql = "select hotel_id from `$compare` where tour_id = $row->id";


            $hotels_ids = $wpdb->get_results($compare_sql);


            $hotels_query = "select * from `$hotels` where ";

            foreach($hotels_ids as $id)
            {
                $hotels_query = $hotels_query . "id = $id->hotel_id or ";
            }

            $hotels_query = substr($hotels_query, 0, -3);

            $tmp = $wpdb->get_results($hotels_query, OBJECT);

            // split hotel apartments

            foreach($tmp as $t)
            {
                $apartments_query = "select * from $apartments_db where hotel_id = $t->id";
                $apartments = $wpdb->get_results($apartments_query);

                $t->apartments = $apartments;
            }

            $row->hotels = $tmp;
        }

        return $output;
    }

    function get_hotels($data)
    {


        $out = array(
            'code' => '0',
            'message' => 'success',
            'id' => $data['id']
        );
        return $out;
    }

    function get_stats($data)
    {

        $key = '-1';
        $key = $data['id'];

        if ($key != '-1')
        {

            global $wpdb;

            $orders = $wpdb->prefix.$this->db_orders;
            $customers = $wpdb->prefix.$this->db_customers;
            $hotels = $wpdb->prefix.$this->db_hotels;
            $tours = $wpdb->prefix.$this->db_tours;
            $apartments = $wpdb->prefix.$this->db_apartments;
			$agents = $wpdb->prefix."luxtour_agents";

			$query_id = "select id from $agents where `key` = '$key'";
			$id = $wpdb->get_var($query_id);


            $query_orders = "select t_order.id, t_tours.title as tour, t_hotels.title as hotel, t_apartments.title as apartments, t_order.date_from, t_order.date_until, t_order.customers_count, t_order.total_price, t_order.date_create, t_order.date_confirm, t_order.status from $orders as t_order, $hotels as t_hotels, $tours as t_tours, $apartments as t_apartments where t_order.agent_id = $id and t_tours.id = t_order.tour_id and t_hotels.id = t_order.hotel_id and t_apartments.id = t_order.apartments_id order by t_order.id desc";


            $output_orders = $wpdb->get_results($query_orders);


            foreach ($output_orders as $key => $value)
            {
                $query_customers = "select *from $customers where order_id = $value->id";
                $result_customers = $wpdb->get_results($query_customers);

                $value->customers = $result_customers;
            }

            return $output_orders;
        }
    }

    function get_statistics_all($data)
    {
        // orders count

        $type = $data['type'];
        $count = $data['count'];
        $key = $data['id'];

        global $wpdb;

        $orders = $wpdb->prefix.$this->db_orders;
        $customers = $wpdb->prefix.$this->db_customers;
        $tours = $wpdb->prefix.$this->db_tours;
        $hotels = $wpdb->prefix.$this->db_hotels;
        $apartments = $wpdb->prefix.$this->db_apartments;
		$agents = $wpdb->prefix."luxtour_agents";

        $output = "";
        $query = "";
        $where = "where agent_id";

        if ($key != '' && $key != 0 && $key != "0")
        {
			$sql_id = "select `id` from $agents where `key` = $key";
			$id = $wpdb->get_var($sql_id);

            $where.=" = $id";
        }

        switch ($type) {
            case 'orders':
                if($count < 1)
                    $query = "select count(id) as count, DATE(date_create) date from $orders $where GROUP BY date";
                else
                    $query = "select count(id) as count, DATE(date_create) date from $orders $where GROUP BY date limit $count";
                break;



            case 'customers':
                if ($count < 1)
                    $query = "select count(id), DATE(date_added) date from $customers GROUP BY date";
                else
                    $query = "select count(id), DATE(date_added) date from $customers GROUP BY date limit $count";
            break;

            case 'sex':

                $query = "select count(id) from $customers where sex = 'men'";
                $query_all = "select count(id) from $customers";
                $output = array
                (
                    'men' => $wpdb->get_var($query),
                    'all' => $wpdb->get_var($query_all),
                );

                return $output;
                break;

            case 'tours':

                $query = "select count(t_orders.id) as count, t_tours.title as title from $orders as t_orders, $tours as t_tours".
                    " $where and t_orders.tour_id = t_tours.id GROUP BY t_tours.title";

                break;

            case 'hotels':

                $query = "select count(t_orders.id) as count, t_hotels.title as title from $orders as t_orders, $hotels as t_hotels".
                    " $where and t_orders.hotel_id = t_hotels.id GROUP BY t_hotels.title";

                break;

            case 'apartments':
                $query = "select count(t_orders.id) as count, t_hotels.title as hotel_title, t_apartments.title as apartments_title from".
                    " $orders as t_orders, $hotels as t_hotels, $apartments as t_apartments".
                    " $where and t_orders.apartments_id = t_apartments.id and t_orders.hotel_id = t_hotels.id GROUP BY t_apartments.title, t_hotels.title ORDER BY count(t_orders.id) desc";
                if ($count > 0)
                    $query.= " limit $count";


                break;

            default:
                $query = "select count(id), DATE(date_create) date from $orders GROUP BY date";
                break;
        }


        return $wpdb->get_results($query);
    }

    function get_news($data)
    {
        $start = $data['start'];
        $count = $data['count'];

        $myposts = get_posts( array(
            'posts_per_page' => $count,
            'offset'         => $start,
        ) );

        return $myposts;
    }

    function xyu()
    {

        $args = array
        (
            'name' => 'xyu',
            'second name' => 'xyu',
            'third name' => 'xyu',
        );

        return $args;

    }

    // Plugin menu options

    function plugin_menu() {
        add_options_page( 'Luxtour test plugin', 'lux-1', 'manage_options', 'lux_1', array('Luxtour_test', 'options') );
    }

    function options() {

        if ( !current_user_can( 'manage_options' ) )  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        echo '<div class="wrap">';
        echo '<p>Here is where the form would go if I actually had options.</p>';
        echo '</div>';
    }

    // self functions

}

$l_api = new luxtour_api();



?>
