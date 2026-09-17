@foreach($conversations as $conversation)
    <hr><hr>
    <h2>{{$conversation->title}}</h2>
    <p>{{$conversation->description}}</p>

    <h4>Consensus:</h4>
    <hr>
    @foreach($conversation->responses as $response)
        <h4>{{$response->responseGroup->name}}</h4>
        <p>{!!nl2br($response->consensus_text)!!}</p>
        <hr>
    @endforeach
@endforeach