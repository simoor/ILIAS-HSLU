select * from ilias_hslu_7_db.tst_solutions ts where
/*question_fi = 777393 and */
        active_fi IN(
        select active_id from ilias_hslu_7_db.tst_active ta where user_fi IN (
            select usr_id from ilias_hslu_7_db.usr_data ud  where lastname = 'Gilardi' and firstname = 'Alessandro'
        ) and test_fi IN (
            select test_id from ilias_hslu_7_db.tst_tests tt where tt.obj_fi IN(
                select obj_id from ilias_hslu_7_db.object_data od where od.obj_id = (
                    select obj_id from ilias_hslu_7_db.object_data od where od.title = '02 Schwingungen' order by obj_id DESC
                    LIMIT 1
                )
            )
        )
    )