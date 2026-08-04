import { useEffect, useState } from 'react';
import { millisecondsUntilNextLocalMidnight, todayIso } from '../lib/dateControls';

export function useTodayDate() {
    const [today, setToday] = useState(() => todayIso());

    useEffect(() => {
        let timer: number;

        const schedule = () => {
            timer = window.setTimeout(() => {
                setToday(todayIso());
                schedule();
            }, millisecondsUntilNextLocalMidnight() + 250);
        };

        schedule();

        return () => window.clearTimeout(timer);
    }, []);

    return today;
}
