@forelse($students as $index => $student)
    <tr @class(['irma-row' => $student->is_irma, 'nonis-row' => $student->is_nonis])><td class="no">{{ (($students->currentPage() - 1) * 50) + $index + 1 }}</td><td class="name">{{ $student->nama }}</td><td class="gender">{{ $student->jenis_kelamin }}</td>
    @foreach($visibleDates as $dateIndex => $date) @php($dateKey = $date->format('Y-m-d')) @php($value = $attendance[$student->id][$dateKey] ?? '') @php($weekKey = $date->modify('monday this week')->format('Y-m-d'))
        <td><select class="attendance-select" aria-label="{{ $student->nama }} {{ $dateKey }}" data-student="{{ $student->id }}" data-date="{{ $dateKey }}" data-status="{{ $value }}"><option value="" @selected($value === '')>-</option><option value="H" @selected($value === 'H')>H</option><option value="A" @selected($value === 'A')>A</option><option value="I" @selected($value === 'I')>I</option><option value="S" @selected($value === 'S')>S</option>@if($student->jenis_kelamin === 'P')<option value="M" @selected($value === 'M')>M</option>@endif</select>@if(in_array($value, ['H', 'M'], true) && isset($attendanceTimes[$student->id][$dateKey]))<small class="attendance-scan-time">{{ $attendanceTimes[$student->id][$dateKey] }}</small>@endif</td>
        @if($date->format('N') === '5' || ($selectedTableWeek !== 'all' && $dateIndex === count($visibleDates) - 1))
            @php($weekly = $attendanceTotals[$student->id]['weekly'][$weekKey] ?? ['A' => 0, 'I' => 0, 'S' => 0, 'H' => 0, 'M' => 0])
            <td class="week"><span class="status-a">{{ $weekly['A'] }}</span> / <span class="status-i">{{ $weekly['I'] }}</span> / <span class="status-s">{{ $weekly['S'] }}</span> / <span class="status-h">{{ $weekly['H'] }}</span> / <span class="status-m">{{ $weekly['M'] }}</span></td>
        @endif
    @endforeach
    @foreach(['A', 'I', 'S', 'H', 'M'] as $status)<td class="total status-{{ strtolower($status) }}">{{ $attendanceTotals[$student->id]['monthly'][$status] }}</td>@endforeach
    </tr>
@empty
    <tr><td colspan="{{ count($visibleDates) + count($weeks) + 8 }}" class="empty">Tidak ada siswa yang cocok di rombel ini. <a href="#attendanceFilters">Ubah pilihan kelas atau pencarian</a></td></tr>
@endforelse