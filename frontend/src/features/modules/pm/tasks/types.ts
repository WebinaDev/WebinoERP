export type TaskChecklistItem = {
  id: string;
  text: string;
  checked: boolean;
};

export type TaskCommentRow = {
  id: number;
  body: string;
  user_id?: number;
  user_name?: string | null;
  created_at?: string | null;
};

export type TaskAttachmentRow = {
  id: number;
  original_name?: string | null;
  path?: string | null;
  url?: string | null;
  size_bytes?: number | null;
  created_at?: string | null;
};

export type TaskTimeLog = {
  minutes: number;
  note?: string | null;
  at?: string | null;
};

export type TaskRow = {
  id: number;
  title?: string | null;
  status?: string | null;
  priority?: string | null;
  label?: string | null;
  project_id?: number | null;
  assignee_id?: number | null;
  due_at?: string | null;
  created_at?: string | null;
  content?: string | null;
  checklist?: TaskChecklistItem[] | null;
  time_logs?: TaskTimeLog[] | null;
  workflow_status_id?: number | null;
};

export type TaskCalendarEvent = {
  id: number;
  title: string;
  due_at: string;
};

export type TaskGanttItem = {
  id: number;
  text: string;
  start_date: string;
  end_date: string;
  duration: number;
  progress: number;
};
