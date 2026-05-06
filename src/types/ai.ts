export interface AiConversation {
  id: number
  title: string
  model: string
  total_tokens: number
  updated_at: string
  messages?: AiMessage[]
}

export interface AiMessage {
  id: number
  conversation_id: number
  role: 'user' | 'assistant' | 'tool'
  content: string | null
  tool_calls: AiToolCall[] | null
  tokens_used: number
  created_at: string
  isStreaming?: boolean
  actions?: AiFrontendAction[]
}

export interface AiToolCall {
  tool: string
  result: Record<string, unknown>
}

export type AiFrontendAction =
  | { type: 'open_compose'; to?: string; subject?: string; body?: string }
  | { type: 'refresh_calendar' }

export interface AiSendRequest {
  message: string
}

export interface AiSendResponse {
  data: AiMessage & { actions: AiFrontendAction[] }
}
