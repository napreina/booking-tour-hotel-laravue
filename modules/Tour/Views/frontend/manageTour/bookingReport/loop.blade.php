<tr>
    <td class="booking-history-type">
        <i class="fa fa-bolt"></i>
        <small>{{$booking->object_model}}</small>
    </td>
    <td>
        @if($service = $booking->service)
            <a target="_blank" href="{{$service->getDetailUrl()}}">
                {{$service->title}}
            </a>
        @else
            {{__("[Deleted]")}}
        @endif
    </td>
    <td class="a-hidden">{{display_date($booking->created_at)}}</td>
    <td class="a-hidden">
        {{__("Check in")}} : {{display_date($booking->start_date)}} <br>
        {{__("Duration")}} : {{ $booking->getMeta("duration") ?? "1"  }} {{__("hours")}}
    </td>
    <td>{{format_money($booking->total)}}</td>
    <td>
       @php($commission_type = json_decode($booking->commission_type,true))
        @if($commission_type['type']=='percent')
        {{__("Percent")}} : {{$commission_type['amount'].'%'}} <br>
            @endif
        {{__("Amount")}} : {{format_money($booking->commission)}} <br>
    </td>
    <td class="{{$booking->status}} a-hidden">{{$booking->statusName}}</td>
    <td width="2%">
        @if($service = $booking->service)
            <a class="btn btn-xs btn-primary btn-info-booking" data-toggle="modal" data-target="#modal-booking-{{$booking->id}}">
                <i class="fa fa-info-circle"></i>{{__("Details")}}
            </a>
            @include ($service->checkout_booking_detail_modal_file ?? '')
        @endif
        @if(!empty(setting_item("tour_allow_vendor_can_change_their_booking_status")))
            <a class="btn btn-xs btn-info btn-make-as" data-toggle="dropdown">
                <i class="icofont-ui-settings"></i>
                {{__("Action")}}
            </a>
            <div class="dropdown-menu">
                @if(!empty($statues))
                    @foreach($statues as $status)
                        <a href="{{ route("tour.vendor.booking_report.bulk_edit" , ['id'=>$booking->id , 'status'=>$status]) }}">
                            <i class="icofont-long-arrow-right"></i> {{__('Mark as: :name',['name'=>ucfirst($status)])}}
                        </a>
                    @endforeach
                @endif
            </div>
        @endif
    </td>
</tr>
