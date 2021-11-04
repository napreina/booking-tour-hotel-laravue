<?php
namespace Modules\Tour\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Booking\Models\Booking;
use Modules\FrontendController;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDate;

class AvailabilityController extends FrontendController{

    protected $tourClass;
    /**
     * @var TourDate
     */
    protected $tourDateClass;
    /**
     * @var Booking
     */
    protected $bookingClass;

    protected $indexView = 'Tour::frontend.user.availability';

    public function __construct()
    {
        parent::__construct();
        $this->tourClass = Tour::class;
        $this->tourDateClass = TourDate::class;
        $this->bookingClass = Booking::class;
    }

    public function index(Request $request){
        $this->checkPermission('tour_create');

        $q = $this->tourClass::query();

        if($request->query('s')){
            $q->where('title','like','%'.$request->query('s').'%');
        }

        if(!$this->hasPermission('tour_manage_others')){
            $q->where('create_user',$this->currentUser()->id);
        }

        $q->orderBy('bravo_tours.id','desc');

        $rows = $q->paginate(15);

        $current_month = strtotime(date('Y-m-01',time()));

        if($request->query('month')){
            $date = date_create_from_format('m-Y',$request->query('month'));
            if(!$date){
                $current_month = time();
            }else{
                $current_month = $date->getTimestamp();
            }
        }
        $breadcrumbs = [
            [
                'name' => __('Tours'),
                'url'  => route('tour.vendor.index')
            ],
            [
                'name'  => __('Availability'),
                'class' => 'active'
            ],
        ];
        $page_title = __('Tours Availability');

        return view($this->indexView,compact('rows','breadcrumbs','current_month','page_title','request'));
    }

    public function loadDates(Request $request){

        $request->validate([
            'id'=>'required',
            'start'=>'required',
            'end'=>'required',
        ]);

        $tour = $this->tourClass::find($request->query('id'));
        if(empty($tour)){
            $this->sendError(__('Tour not found'));
        }

        $query = $this->tourDateClass::query();
        $query->where('target_id',$request->query('id'));
        $query->where('start_date','>=',date('Y-m-d H:i:s',strtotime($request->query('start'))));
        $query->where('end_date','<=',date('Y-m-d H:i:s',strtotime($request->query('end'))));
        $rows =  $query->take(40)->get();
        $allDates = [];
        for($i = strtotime($request->query('start')); $i <= strtotime($request->query('end')); $i+= DAY_IN_SECONDS)
        {
            $date = [
                'id'=>rand(0,999),
                'active'=>0,
                'price'=>(!empty($tour->sale_price) and $tour->sale_price > 0 and $tour->sale_price < $tour->price) ? $tour->sale_price : $tour->price,
                'is_default'=>true,
                'textColor'=>'#2791fe'
            ];
            $date['price_html'] = format_money($date['price']);
            $date['max_guests'] = $tour->max_people;
            $date['title'] = $date['event']  = $date['price_html'];
            $date['start'] = $date['end'] = date('Y-m-d',$i);
            if($tour->default_state){
                $date['active'] = 1;
            }else{
                $date['title'] = $date['event'] = __('Blocked');
                $date['backgroundColor'] = 'orange';
                $date['borderColor'] = '#fe2727';
                $date['classNames'] = ['blocked-event'];
                $date['textColor'] = '#fe2727';
            }
            
            if($request->input('for_single')){
                if(empty($tour->max_people) and $tour->max_people < 1){
                    $date['active'] = 0;
                }
            }

            if(!empty($tour->meta->enable_person_types) and $tour->meta->enable_person_types==1){
                $date['person_types'] = $tour->meta->person_types ;
                if(!empty( $date['person_types'] )){
                    $c_title = "";
                    foreach (  $date['person_types']  as &$person){
                        $c_title .= $person['name'].": ".format_money($person['price'])."<br>";
                        //for single
                        $person['display_price'] = format_money($person['price']);
                        $person['number'] = $person['min'] ?? 0;
                    }
                    $date['title'] = $date['event']  = $c_title;
                }
            }

            // Open Hours
            if (!empty($tour->meta->enable_open_hours) and $tour->meta->enable_open_hours == 1) {
                $open_hours = $tour->meta->open_hours;
                $nDate = date('N', $i);
                if (!isset($open_hours[$nDate]) or empty($open_hours[$nDate]['enable'])) {
                    $date['active'] = 0;
                }
            }

            $allDates[date('Y-m-d',$i)] = $date;
        }
        if(!empty($rows))
        {
            foreach ($rows as $row)
            {
                $row->start = date('Y-m-d',strtotime($row->start_date));
                $row->end = date('Y-m-d',strtotime($row->start_date));
                $row->textColor = '#2791fe';
                $row->title = $row->event = format_money($row->price);

                if($request->input('for_single')){
                    if(empty($row->max_guests) and $row->max_guests < 1 ){
                        $row->active = 0;
                    }
                }

                if(!$row->active)
                {
                    $row->title = $row->event = __('Blocked');
                    $row->backgroundColor = '#fe2727';
                    $row->classNames = ['blocked-event'];
                    $row->textColor = '#fe2727';
                    $row->active = 0;
                }else{
                    $row->classNames = ['active-event'];
                    $row->active = 1;
                    $list_person_types = null;
                    if(!empty($tour->meta->enable_person_types) and $tour->meta->enable_person_types==1){
                        $list_person_types = $row->person_types;
                        if(!empty($list_person_types) and is_array($list_person_types)){
                            $c_title = "";
                            foreach ($list_person_types as &$person){
                                $c_title .= $person['name'].": ".format_money($person['price'])."<br>";
                                //for single
                                $person['display_price'] = format_money($person['price']);
                                $person['number'] = $person['min'] ?? 0;
                            }
                            $row->title  = $c_title;
                        }
                    }
                    $row->person_types = $list_person_types;
                    // Open Hours
                    if (!empty($tour->meta->enable_open_hours) and $tour->meta->enable_open_hours == 1) {
                        $open_hours = $tour->meta->open_hours;
                        $nDate = date('N', strtotime($row->start_date));
                        if (!isset($open_hours[$nDate]) or empty($open_hours[$nDate]['enable'])) {
                            $row->active = 0;
                        }
                    }

                }
                $allDates[date('Y-m-d',strtotime($row->start_date))] = $row->toArray();
            }
        }

        $bookings = $this->bookingClass::getBookingInRanges($tour->id,$tour->type,$request->query('start'),$request->query('end'));
        if(!empty($bookings))
        {
            foreach ($bookings as $booking){
                for($i = strtotime($booking->start_date); $i <= strtotime($booking->end_date); $i+= DAY_IN_SECONDS){
                    if(isset($allDates[date('Y-m-d',$i)])){
                        $total_guests_booking = $booking->total_guests;
                        $max_guests = $allDates[date('Y-m-d',$i)]['max_guests'];
                        if($total_guests_booking >= $max_guests){
                            $allDates[date('Y-m-d',$i)]['active'] = 0;
                            $allDates[date('Y-m-d',$i)]['event'] = __('Full Book');
                            $allDates[date('Y-m-d',$i)]['title'] = __('Full Book');
                            $allDates[date('Y-m-d',$i)]['classNames'] = ['full-book-event'];
                        }
                    }
                }
            }
        }

        $data = array_values($allDates);

        return response()->json($data);
    }

    public function store(Request $request){

        $request->validate([
            'target_id'=>'required',
            'start_date'=>'required',
            'end_date'=>'required'
        ]);

        $tour = $this->tourClass::find($request->input('target_id'));
        $target_id = $request->input('target_id');

        if(empty($tour)){
            $this->sendError(__('Tour not found'));
        }

        if(!$this->hasPermission('tour_manage_others')){
            if($tour->create_user != Auth::id()){
                $this->sendError("You do not have permission to access it");
            }
        }

        $postData = $request->input();
        if(!empty($person_types = $postData['person_types']) and is_array($person_types)){
            foreach ($person_types as &$item){
                $item['display_price'] = format_money($item['price']);
            }
            $postData['person_types'] = $person_types;
        }

        for($i = strtotime($request->input('start_date')); $i <= strtotime($request->input('end_date')); $i+= DAY_IN_SECONDS)
        {
            $date = $this->tourDateClass::where('start_date',date('Y-m-d',$i))->where('target_id',$target_id)->first();
            if(empty($date)){
                $date = new $this->tourDateClass();
                $date->target_id = $target_id;
            }
            $postData['start_date'] = date('Y-m-d H:i:s',$i);
            $postData['end_date'] = date('Y-m-d H:i:s',$i);
            $date->fillByAttr([
                'start_date','end_date','price',
                'max_guests',
                'active',
                'person_types'
            ],$postData);
            $date->save();
        }
        $this->sendSuccess([],__("Update Success"));
    }
}
