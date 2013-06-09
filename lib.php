<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Class repository_areafilesplus
 *
 * @package   repository_areafilesplus
 * @copyright 2013 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Main class responsible for files listing in repostiory_areafilesplus
 *
 * @package   repository_areafilesplus
 * @copyright 2013 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_areafilesplus extends repository {
    /**
     * Areafiles Plus plugin doesn't require login, so list all files
     *
     * @return mixed
     */
    public function print_login() {
        return $this->get_listing();
    }

    /**
     * Get file listing
     *
     * @param string $path
     * @param string $path not used by this plugin
     * @return mixed
     */
    public function get_listing($path = '', $page = '') {
        global $USER, $OUTPUT;
        $itemid = optional_param('itemid', 0, PARAM_INT);
        $env = optional_param('env', 'filepicker', PARAM_ALPHA);
        $ret = array(
            'dynload' => true,
            'nosearch' => true,
            'nologin' => true,
            'list' => array(),
        );
        if (empty($itemid) || $env !== 'editor') {
            return $ret;
        }

        // Form URL to manage files
        $areacontextid = optional_param('ctx_id', SYSCONTEXTID, PARAM_INT);
        $areacontext = context::instance_by_id($areacontextid);
        if (has_capability('repository/areafilesplus:manage', $areacontext)) {
            $maxbytes = optional_param('maxbytes', 0, PARAM_INT);
            $manageurl = new moodle_url('/repository/areafilesplus/manage.php',
                    array('itemid' => $itemid, 'maxbytes' => $maxbytes, 'ctx_id' => $areacontextid));
            $ret['message'] = "<a href=\"#\" onclick=\"w=window.open('".$manageurl->out(false)."', 'areafilesplusmanage', 'fullscreen=no,width=800,height=600'); w.focus(); return false;\">".
                    '<img src="'.$OUTPUT->pix_url('a/setting').'"> '.
                    get_string('manageurl', 'repository'). "</a>";
        }

        $context = context_user::instance($USER->id);
        $fs = get_file_storage();
        $files = $fs->get_directory_files($context->id, 'user', 'draft', $itemid, '/');
        foreach ($files as $file) {
            if ($file->is_directory()) {
                // Files embedded in texteditor do not support subfolders
                continue;
            }
            $fileurl = moodle_url::make_draftfile_url($itemid, $file->get_filepath(), $file->get_filename());
            $node = array(
                'title' => $file->get_filename(),
                'size' => $file->get_filesize(),
                'source' => $fileurl->out(),
                'datemodified' => $file->get_timemodified(),
                'datecreated' => $file->get_timecreated(),
                'author' => $file->get_author(),
                'license' => $file->get_license(),
                'isref' => $file->is_external_file(),
                'icon' => $OUTPUT->pix_url(file_file_icon($file, 24))->out(false),
                'thumbnail' => $OUTPUT->pix_url(file_file_icon($file, 90))->out(false)
            );
            if ($file->get_status() == 666) {
                $node['originalmissing'] = true;
            }
            if ($imageinfo = $file->get_imageinfo()) {
                $node['realthumbnail'] = $fileurl->out(false, array('preview' => 'thumb', 'oid' => $file->get_timemodified()));
                $node['realicon'] = $fileurl->out(false, array('preview' => 'tinyicon', 'oid' => $file->get_timemodified()));
                $node['image_width'] = $imageinfo['width'];
                $node['image_height'] = $imageinfo['height'];
            }
            $ret['list'][] = $node;
        }
        $ret['list'] = array_filter($ret['list'], array($this, 'filter'));
        return $ret;
    }

    /**
     * This plugin only can return link
     *
     * @return int
     */
    public function supported_returntypes() {
        return FILE_EXTERNAL;
    }
}