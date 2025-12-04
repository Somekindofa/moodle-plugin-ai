/**
 * @typedef {Object} Document
 * @property {string} page_content
 * @property {Object} metadata
 */

/**
 * @typedef {Object} ConversationState
 * @property {any[]} messages
 * @property {Document[]} context
 * @property {Record<string, any>|null} video_metadata
 * @property {string|null} enhanced_query
 */