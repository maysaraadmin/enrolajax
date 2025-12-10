import $ from 'jquery';
import {call as fetchMany} from 'core/ajax';
import {get_string as getString} from 'core/str';

const enrol = (userid, courseid) =>
    fetchMany([{
        methodname: 'local_enrolajax_enrol',
        args: {userid, courseid},
    }])[0];

export const init = () => {
    $('#id_enrol').on('click', async e => {
        e.preventDefault();
        const userid   = $('[name="userid"]').val();
        const courseid = $('[name="courseid"]').val();
        if (!userid || !courseid) return;

        const rsp = await enrol(userid, courseid);
        alert(rsp.message); // quick feedback – replace with toast if you prefer
    });
};