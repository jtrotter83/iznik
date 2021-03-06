<?php
function image() {
    global $dbhr, $dbhm;

    $ret = [ 'ret' => 100, 'status' => 'Unknown verb' ];
    $id = intval(presdef('id', $_REQUEST, 0));
    $msgid = pres('msgid', $_REQUEST) ? intval($_REQUEST['msgid']) : NULL;
    $fn = presdef('filename', $_REQUEST, NULL);
    $identify = array_key_exists('identify', $_REQUEST) ? filter_var($_REQUEST['identify'], FILTER_VALIDATE_BOOLEAN) : FALSE;
    $ocr = array_key_exists('ocr', $_REQUEST) ? filter_var($_REQUEST['ocr'], FILTER_VALIDATE_BOOLEAN) : FALSE;
    $group = presdef('group', $_REQUEST, NULL);
    $newsletter = presdef('newsletter', $_REQUEST, NULL);
    $communityevent = presdef('communityevent', $_REQUEST, NULL);
    $chatmessage = presdef('chatmessage', $_REQUEST, NULL);
    $user = presdef('user', $_REQUEST, NULL);

    $sizelimit = 800;
    
    if ($chatmessage) {
        $type = Attachment::TYPE_CHAT_MESSAGE;
        $shorttype = '_m';
    } else if ($communityevent) {
        $type = Attachment::TYPE_COMMUNITY_EVENT;
        $shorttype = '_c';
    } else if ($newsletter) {
        $type = Attachment::TYPE_NEWSLETTER;
        $shorttype = '_n';
    } else if ($group) {
        $type = Attachment::TYPE_GROUP;
        $shorttype = '_g';
    } else if ($user) {
        $type = Attachment::TYPE_USER;
        $shorttype = '_u';
    } else {
        $type = Attachment::TYPE_MESSAGE;
        $shorttype = '';
    }

    switch ($_REQUEST['type']) {
        case 'GET': {
            $a = new Attachment($dbhr, $dbhm, $id, $type);
            $data = $a->getData();
            $i = new Image($data);

            $ret = [
                'ret' => 1,
                'status' => "Failed to create image $id of type $type"
            ];

            if ($i->img) {
                $w = intval(presdef('w', $_REQUEST, $i->width()));
                $h = intval(presdef('h', $_REQUEST, $i->height()));

                if (($w > 0) || ($h > 0)) {
                    # Need to resize
                    $i->scale($w, $h);
                }

                $ret = [
                    'ret' => 0,
                    'status' => 'Success',
                    'img' => $i->getData()
                ];
            }

            break;
        }

        case 'POST': {
            $ret = [ 'ret' => 1, 'status' => 'No photo provided' ];

            # This next line is to simplify UT.
            $rotate = pres('rotate', $_REQUEST) ? intval($_REQUEST['rotate']) : NULL;

            if ($rotate) {
                # We want to rotate.  Do so.
                $a = new Attachment($dbhr, $dbhm, $id, $type);
                $data = $a->getData();
                $i = new Image($data);
                $i->rotate($rotate);
                $newdata = $i->getData(100);
                $a->setData($newdata);

                $ret = [
                    'ret' => 0,
                    'status' => 'Success',
                    'rotatedsize' => strlen($newdata)
                ];
            } else {
                $photo = presdef('photo', $_FILES, NULL) ? $_FILES['photo'] : $_REQUEST['photo'];
                $imgtype = presdef('imgtype', $_REQUEST, Attachment::TYPE_MESSAGE);
                $mimetype = presdef('type', $photo, NULL);

                # Make sure what we have looks plausible - the file upload plugin should ensure this is the case.
                if ($photo &&
                    pres('tmp_name', $photo) &&
                    strpos($mimetype, 'image/') === 0) {

                    # We may need to rotate.
                    $data = file_get_contents($photo['tmp_name']);
                    $image = imagecreatefromstring($data);
                    $exif = exif_read_data($photo['tmp_name']);

                    if($exif && !empty($exif['Orientation'])) {
                        switch($exif['Orientation']) {
                            case 8:
                                $image = imagerotate($image,90,0);
                                break;
                            case 3:
                                $image = imagerotate($image,180,0);
                                break;
                            case 6:
                                $image = imagerotate($image,-90,0);
                                break;
                        }

                        ob_start();
                        imagejpeg($image, NULL, 100);
                        $data = ob_get_contents();
                        ob_end_clean();
                    }

                    if ($data) {
                        $a = new Attachment($dbhr, $dbhm, NULL, $imgtype);
                        $id = $a->create($msgid, $photo['type'], $data);

                        # Make sure it's not too large, to keep DB size down.  Ought to have been resized by
                        # client, but you never know.
                        $data = $a->getData();
                        $i = new Image($data);
                        $h = $i->height();
                        $w = $i->width();

                        if ($w > $sizelimit) {
                            $h = $h * $sizelimit / $w;
                            $w = $sizelimit;
                            $i->scale($w, $h);
                            $data = $i->getData(100);
                            $a->setPrivate('data', $data);
                        }

                        $ret = [
                            'ret' => 0,
                            'status' => 'Success',
                            'id' => $id,
                            'path' => $a->getPath(FALSE),
                            'paththumb' => $a->getPath(TRUE)
                        ];

                        # Return a new thumbnail (which might be a different orientation).
                        $ret['initialPreview'] =  [
                            '<img src="' . $a->getPath(TRUE) . '" class="file-preview-image" width="130px">',
                        ];

                        if ($identify) {
                            $a = new Attachment($dbhr, $dbhm, $id);
                            $ret['items'] = $a->identify();
                        }

                        if ($ocr) {
                            $a = new Attachment($dbhr, $dbhm, $id, $type);
                            $ret['ocr'] = $a->ocr();
                        }
                    }
                }

                # Uploader code requires this field.
                $ret['error'] = $ret['ret'] == 0 ? NULL : $ret['status'];
            }

            break;
        }
    }

    return($ret);
}
