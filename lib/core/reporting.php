<?php
/*
    Function description
*/
function reportAdd($report) {
    // Array should minimally contain $source(string), $ip(string), $class(string), $type(string), $timestamp(int), $information(array)
    if (!is_array($report)) {
        return false;
    } else {
        extract($report);
    }

    if (!isset($ip) || !isset($source) || !isset($class) || !isset($type) || !isset($timestamp)) {
        logger(LOG_WARNING, __FUNCTION__ . " was called with not enough arguments in the array");
        return false;
    }
    if (!isset($domain)) {
        $domain = '';
    } elseif(preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
        $domain = $regs['domain'];
    } else {
        // Its fine as it is
    }

    $select  = "SELECT * FROM Reports";
    $filteradd = "";
    if (isset($customer) && is_array($customer) && !empty($customer['Code']) && !empty($customer['AutoNotify'])) {
        $filteradd = "AND CustomerCode='${customer['Code']}'";
    }
    $matchperiod = $timestamp - strtotime(REPORT_MATCHING . " ago");
    $filter  = "WHERE IP='${ip}' AND Domain LIKE '%${domain}%' AND Source='${source}' AND Class='${class}' AND LastSeen > '${matchperiod}' AND Status != 'CLOSED' ${filteradd} ORDER BY LastSeen DESC LIMIT 1;";
    $query   = "${select} ${filter}";
    $count   = _mysqli_num_rows($query);

    if (!isset($domain)) {
        $domain = "";
    }
    if (!isset($uri)) {
        $uri    = "";
    }

    if ($count > 1) {
        // This should never happen
        return false;

    } elseif ($count === 1) {
        $result = _mysqli_fetch($query);
        if (empty($result)) return false;

        $row = $result[0];
        if ($row['LastSeen'] == $timestamp) {
            logger(LOG_WARNING, __FUNCTION__ . " by $source ip $ip class $class seen " . date("d-m-Y H:i:s",$timestamp) . " is DUPLICATE!");
            return true;
        } elseif ($row['LastSeen'] >= $timestamp) {
            logger(LOG_WARNING, __FUNCTION__ . " by $source ip $ip class $class seen " . date("d-m-Y H:i:s",$timestamp) . " is OBSOLETE!");
            return true;
        } else {
            // TODO when updating not only update timestamp but also match information if there is something new to add
            $update = "UPDATE Reports SET LastSeen='${timestamp}', ReportCount='".($row['ReportCount'] + 1)."'";
            $query  = "${update} ${filter}";
            if (_mysqli_query($query, "")) {
                logger(LOG_DEBUG, __FUNCTION__ . " by $source ip $ip class $class seen " . date("d-m-Y H:i:s",$timestamp) . " is UPDATED");
                return $row['ID'];
            }
            return false;
        }
    } else {
        if (!isset($customer) || !is_array($customer)) {
            $customer = CustomerLookup($ip);
        } else {
            if(empty($customer['Code'])) {
                logger(LOG_ERR, __FUNCTION__ . " was incorrectly called with empty customer information");
                return false;
            }
        }

        $query = "INSERT INTO Reports (
                                        Source, 
                                        IP, 
                                        Domain, 
                                        URI, 
                                        FirstSeen, 
                                        LastSeen, 
                                        Information, 
                                        Class,
                                        Type,
                                        CustomerCode, 
                                        CustomerName,
                                        CustomerContact, 
                                        CustomerResolved,
                                        CustomerIgnored,
                                        Status,
                                        AutoNotify,
                                        NotifiedCount, 
                                        ReportCount,
                                        LastNotifyReportCount,
                                        LastNotifyTimestamp
                            ) VALUES (
                                        \"${source}\", 
                                        \"${ip}\", 
                                        \"${domain}\", 
                                        \"${uri}\", 
                                        \"${timestamp}\", 
                                        \"${timestamp}\", 
                                        \"" . mysql_escape_string(json_encode($information)) . "\", 
                                        \"${class}\", 
                                        \"${type}\",
                                        \"${customer['Code']}\", 
                                        \"${customer['Name']}\",
                                        \"${customer['Contact']}\", 
                                        \"0\", 
                                        \"0\",
                                        \"OPEN\",
                                        \"".((empty($customer['AutoNotify']))?0:1)."\",
                                        \"0\", 
                                        \"1\",
                                        \"0\",
                                        \"0\"
                            );";

        $result = _mysqli_query($query);
        if ($result) {
            if(function_exists('custom_notifier')) {
                logger(LOG_DEBUG, __FUNCTION__ . " is calling custom_notifier");
                $report['customer'] = $customer;
                custom_notifier($report);
            }

            logger(LOG_DEBUG, __FUNCTION__ . " by $source ip $ip class $class seen " . date("d-m-Y H:i:s",$timestamp));
            return $result;
        }
        return false;

    }
}


/*
    Function description
*/
function reportList($filter) {
    $reports = array();
    $query = "SELECT * FROM Reports WHERE 1 ${filter}";

    $reports = _mysqli_fetch($query);
    return $reports;
}


/*
    Function description
*/
function reportCount($filter) {
    $reports = array();
    $query = "SELECT COUNT(*) as Count FROM Reports WHERE 1 ${filter}";
    $reports = _mysqli_fetch($query);
    if (!empty($reports[0]['Count'])) return $reports[0]['Count'];
    return 0;
}


/*
    Function description
*/
function reportGet($id) {
    $reports = array();

    $filter  = "AND ID='${id}'";
    $query   = "SELECT * FROM Reports WHERE 1 ${filter}";

    $report = _mysqli_fetch($query);

    if (isset($report[0])) {
        return $report[0];
    } else {
        return false;
    }
}


/*
    Function description
*/
function reportSummary($period) {
    $summary = array();

    $filter = "";
    $query  = "SELECT Class, count(*) AS Count FROM Reports GROUP BY Class";

    $summary = _mysqli_fetch($query);

    return $summary;
}


/*
    Function description

    accepts only timestamp as argument now
*/
function reportIps($period) {
    $summary = array();

    $query  = "SELECT DISTINCT Ip FROM Reports WHERE LastSeen > $period";

    if ($ips = _mysqli_fetch($query)) {
        $ret = array();
        foreach ($ips as $ip) $ret[] = $ip['Ip'];
        return $ret;
    }

    return false;
}


/*
    Function description
*/
function reportMerge() {

}


/*
    Function description
*/
function reportResolved($ticket) {
    $query = "Update Reports SET CustomerResolved = '1' WHERE 1 AND ID = '${ticket}'";

    $result = _mysqli_query($query, "");

    return $result;
}


/*
    Function description
*/
function reportIgnored($ticket) {
    $query = "Update Reports SET CustomerIgnored = '1' WHERE 1 AND ID = '${ticket}'";

    $result = _mysqli_query($query, "");

    return $result;
}


/*
    Function description
*/
function reportClosed($ticket) {
    $query = "Update Reports SET Status = 'CLOSED' WHERE 1 AND ID = '${ticket}'";

    $result = _mysqli_query($query, "");

    return $result;
}



/*
    Function description
*/
function ReportContactupdate($ticket) {

    $report = reportGet($ticket);

    $customer = custom_find_customer($report['IP']);

    if (isset($customer['Code']) && $customer['Code'] != $result['CustomerCode']) {
        echo "{$result['IP']} OLD ${result['CustomerCode']} => ${customer['Code']}". PHP_EOL;

        $query = "UPDATE `Reports` SET CustomerCode='${customer['Code']}', CustomerName='${customer['Name']}', CustomerContact='${customer['Contact']}' WHERE ID='${ticket}';";
        _mysqli_query($query, "");

        return true;
    } else {
        return false;
    }
}


/*
    Function description
*/
function reportNotified($ticket) {
    // Subfunction for reportNotification which can be called when a notifier
    // successfully send out the notification to mark the ticket as notified
    //
    // Set the notifyCount + 1, Set the LastNotifyReportCount to ReportCount
    $query = "Update Reports SET LastNotifyReportCount = ReportCount, NotifiedCount = NotifiedCount+1, LastNotifyTimestamp = '".time()."' WHERE 1 AND ID = '${ticket}'";

    $result = _mysqli_query($query, "");

    return $result;    
}


/*
    Function description sends out notifications based on a filter (array):

$filter = array(
                // Send out for a specific ticket
                'Ticket'    => '4411',

                // Send out for a specific IP
                'IP'        => '1.1.1.1',

                // Send out for a specific customer
                'Customer'  => 'UNDEF',

                // Send out everthing thats considered unhandled
                'All'       => true,

                // How many days to look back (don't notify about old obsolete reports)
                'Days'      => 3
               );
*/
function reportSend($filter) {

    if (!isset($filter) && !is_array($filter)) {
        return false;
    }
    if (empty($filter['Ticket']) && empty($filter['IP']) && empty($filter['Customer']) && empty($filter['All'])) {
        return false;
    }

    if (null === NOTIFICATIONS && !is_file(APP.NOTIFICATION_TEMPLATE)){
        return false;
    } else {
        $template = file_get_contents(APP.NOTIFICATION_TEMPLATE);
    }

    $counter= 0;

    logger(LOG_DEBUG, "Notifier - Is starting a run");

    // Collect reports - return all the data so you can decide what to put
    // in the customer mail. format: array($reports[CustomerCode][$i][$report_elements])
    $allreports = reportNotification($filter);

    $class_seen = array();

    foreach($allreports as $customerCode => $reports) {
        $count = count($reports);

        $blocks = "";
        foreach($reports as $id => $report) {
            $block = array();
            $report['Information'] = json_decode($report['Information']);

            if (SELF_HELP_URL != "") {
                $token = md5("${report['ID']}${report['IP']}${report['Class']}");
                $selfHelpLink = SELF_HELP_URL . "?id=${report['ID']}&token=" . $token;
            } else {
                $selfHelpLink = "";
            }

            $block[] = "";
            $block[] = "Ticket #${report['ID']}: Report for IP address ${report['IP']} (${report['Type']}: ${report['Class']})";
            $block[] = "";
            $block[] = "Report date: ".date('Y-m-d H:i',$report['LastSeen']);
            $block[] = "Source: ${report['Source']}";
            if (!empty($selfHelpLink)) $block[] = "Reply or help: " . $selfHelpLink;
            if (!empty($report['Information'])) {
                $block[] = "Report information:";
                if(isset($report['Information']->Domain)) $block[] = "  - domain: " . str_replace('.','[.]',$report['Information']->Domain);
                if(isset($report['Information']->URI)) $block[] = "  - uri/path: " . $report['Information']->URI;
                $report['Information']->Address = $report['IP'];
                foreach($report['Information'] as $field => $value) {
                    // If the value contains a domain name, escape it so spam filters won't flag this abuse report
                    if (in_array($field,array('cc_dns','domain','host','url','http_host'))) $value = str_replace('.','[.]',$value);
                    $block[] = "  - ${field}: ${value}";
                }
            }
            $block[] = "\n";
            $blocks .= implode("\n", $block);

            //Mark the report as notified:
            reportNotified($report['ID']);

            $class_seen[$report['Class']] = 1;

        }

        // Include further information about the abuse reports
        if (!empty($class_seen)) {
            $blocks .= "\nAdditional information:\n\n";
            foreach ($class_seen as $class => $true) {
                if ($class_info = getClassInfo($class)) {
                    $blocks .= "$class:\n\n$class_info\n";
                }
            }
        }

        if (DEBUG === true) {
            $to =           NOTIFICATIONS_FROM_ADDRESS;
        } else {
            $to             = $report['CustomerContact'];
        }
        $email              = $template;
        $subject            = 'Notification of (possible) abuse';
        $email              = str_replace("<<COUNT>>", $count, $email);
        $email              = str_replace("<<BOXES>>", $blocks, $email);

        // Validate all the email addresses in the TO field
        if (!empty($to) && strpos(",", $to) !== false) {
            if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $validated = true;
            } else {
                $validated = false;
            }
        } else {
            $to = str_replace(" ", "", $to);
            $addresses = explode(",", $to);
            foreach($addresses as $address) {
                if (filter_var($address, FILTER_VALIDATE_EMAIL)) {
                    $validated = true;
                } else {
                    $validated = false;
                }
            }
        }

        $headers   = array();
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-type: text/plain; charset=iso-8859-1";
        $headers[] = "From: " . NOTIFICATIONS_FROM_NAME . " <" . NOTIFICATIONS_FROM_ADDRESS . ">";
        $headers[] = "Reply-To: " . NOTIFICATIONS_FROM_NAME . " <" . NOTIFICATIONS_FROM_ADDRESS . ">";
        $headers[] = "X-Mailer: AbuseIO/".VERSION;

        if ($validated) {
            if(mail($to, $subject, $email, implode("\r\n", $headers))) {
                logger(LOG_DEBUG, "Notifier - Successfully sent notification to ${to}");
                $counter++;
            } else {
                logger(LOG_ERR, "Notifier - Failed sending mail to ${to} MTA returned false");
                return false;
            }
        } else {
            logger(LOG_ERR, "Notifier - Failed sending mail to ${to} as the addres is incorrectly formatted");
            return false;
        }
    }

    logger(LOG_DEBUG, "Notifier - Completed and has sent out {$counter} notifications");
    return true;
}


/*
    Function description
*/
function reportNotification($filter) {
    // First we will create an selection
    // Ticket, IP, Customer reports are hooks from the GUI/CLI and will e-mail
    // Everything thats not resolved and will not look at LastNotifyReportCount
    //
    // When sending out all reports, we look at the LastNotifyReportCount to see
    // if new reports came in and the count does not match and the customer has
    // the flag AutoNotify enabled.
    //
    // This will return an array of rows to be notified. so we can combine 
    // all items per customer

    $data  = array();
    $query = "SELECT * FROM Reports WHERE 1 ";

    if (!is_array($filter)) {
        return false;
    } elseif (isset($filter['Ticket'])) {
        // Notify for this ticket only
        $query .= "AND ID = '${filter['Ticket']}' ";

    } elseif (isset($filter['IP'])) {
        // Notify everything about this IP
        $query .= "AND IP = '${filter['IP']}' ";

    } elseif (isset($filter['Customer'])) {
        // Notify everything about this Customer(code)
        $query .= "AND CustomerCode = '${filter['Customer']}' ";

    } elseif (isset($filter['Days'])) {
        $from = time()-(86400*$filter['Days']);
        $query .= "AND LastSeen >= $from ";

    } elseif (isset($filter['All'])) {
        $query .= "AND AutoNotify = '1' ";

    } else {
        return false;
    }

    $interval_info_after  = strtotime(NOTIFICATIONS_INFO_INTERVAL . " ago");
    $interval_abuse_after = strtotime(NOTIFICATIONS_ABUSE_INTERVAL . " ago");

    foreach(_mysqli_fetch($query) as $id => $row) {
        if(isset($filter['All']) && $row['ReportCount'] == $row['LastNotifyReportCount']) {
            // Already notified, nothing new to report

        } elseif($row['CustomerCode'] == "UNDEF") {
            // Customer is not found, therefore we cannot send notifications

        } elseif($row['CustomerIgnored'] == 1) {
            // Customer does not want any more notifications from this report

        } elseif(isset($filter['All']) && $row['ReportCount'] != $row['LastNotifyReportCount'] && $row['AutoNotify'] == '1') {
            // The 'all' filter is called by the cronned notifier for automatic notifications
            // It will check based on the NOTIFICATION_INFO_INTERVAL and NOTIFICATION_ABUSE_INTERVAL is a case is to be 
            // sent out. However if the case was marked as resolved it should always send out the notification again and
            // unset the customerResolved flag. Also the customers AutoNotify must be enabled for notifications to be send.
            if ($row['Type'] == 'INFO' && $row['LastNotifyTimestamp'] < $interval_info_after) {
                $data[$row['CustomerCode']][] = $row;

            } elseif ($row['Type'] == 'ABUSE' && $row['LastNotifyTimestamp'] < $interval_abuse_after) {
                $data[$row['CustomerCode']][] = $row;

            } elseif ($row['Type'] == 'ALERT') {
                $data[$row['CustomerCode']][] = $row;

            } else {
                // Skip this report for notification

            }

        } else {
            $data[$row['CustomerCode']][] = $row;

        }
    }

    return $data;
}

/*
    Get additional information for a class
*/
function getClassInfo($class) {
    $class_file = APP.'/lib/templates/class/'.preg_replace('/( )+/','_',preg_replace('@/@','',strtolower($class))).'.txt';
    if (file_exists($class_file)) {
        return file_get_contents($class_file);
    } else {
        return false;
    }
}

?>
