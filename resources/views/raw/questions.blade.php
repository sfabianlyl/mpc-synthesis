@php
    $count=1;
@endphp
@foreach($questions as $question)
    [{{$count}}]
    <p>{{$question->question}}</p>
    @php
        $count++;
    @endphp
@endforeach