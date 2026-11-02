<?php

return [
    'EventType' => [
        'login' => 'Login',
        'transfer' => 'Transfer',
        'add_beneficiary' => 'Add beneficiary',
        'change_phone' => 'Change phone',
        'raise_limit' => 'Raise limit',
        'open_account' => 'Open account',
    ],
    'AccountStatus' => [
        'active' => 'Active',
        'frozen' => 'Frozen',
        'closed' => 'Closed',
    ],
    'TransactionStatus' => [
        'pending' => 'Pending',
        'completed' => 'Completed',
        'held' => 'Held',
        'blocked' => 'Blocked',
        'rejected' => 'Rejected',
    ],
    'Decision' => [
        'allow' => 'Allow',
        'challenge' => 'Challenge',
        'block' => 'Block',
    ],
    'Priority' => [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ],
    'AlertStatus' => [
        'open' => 'Open',
        'in_review' => 'In review',
        'escalated' => 'Escalated',
        'pending_info' => 'Pending info',
        'closed' => 'Closed',
    ],
    'AnalystDecision' => [
        'confirmed_fraud' => 'Confirmed fraud',
        'false_positive' => 'False positive',
        'escalated' => 'Escalated',
        'info_requested' => 'Info requested',
    ],
    'CaseStatus' => [
        'open' => 'Open',
        'investigating' => 'Investigating',
        'pending_approval' => 'Pending approval',
        'closed' => 'Closed',
    ],
    'OtpStatus' => [
        'pending' => 'Pending',
        'verified' => 'Verified',
        'failed' => 'Failed',
        'expired' => 'Expired',
    ],
    'ApprovalStatus' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],
    'ApprovalType' => [
        'freeze_account' => 'Freeze account',
        'unfreeze_account' => 'Unfreeze account',
        'close_high_value_case' => 'Close high-value case',
        'freeze_beneficiary' => 'Freeze beneficiary',
    ],
    'AccountActionType' => [
        'block_transaction' => 'Block transaction',
        'require_otp' => 'Require OTP',
        'freeze_account' => 'Freeze account',
        'unfreeze_account' => 'Unfreeze account',
        'freeze_beneficiary' => 'Freeze beneficiary',
    ],
    'AccountActionStatus' => [
        'pending_approval' => 'Pending approval',
        'executed' => 'Executed',
        'rejected' => 'Rejected',
    ],
    'AiProposalStatus' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],
    'LabelSource' => [
        'analyst' => 'Analyst',
        'customer' => 'Customer',
        'investigation' => 'Investigation',
        'bank' => 'Bank',
    ],
    'CustomerReportStatus' => [
        'new' => 'New',
        'reviewing' => 'Reviewing',
        'resolved' => 'Resolved',
    ],
    'RuleSuggestionStatus' => [
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
    ],
    'WatchlistType' => [
        'blacklist' => 'Blacklist',
        'whitelist' => 'Whitelist',
    ],
    'WatchlistEntity' => [
        'device' => 'Device',
        'account' => 'Account',
        'ip' => 'IP address',
        'beneficiary' => 'Beneficiary',
    ],
    'ModelStatus' => [
        'champion' => 'Champion',
        'shadow' => 'Shadow',
        'candidate' => 'Candidate',
        'archived' => 'Archived',
    ],
];
