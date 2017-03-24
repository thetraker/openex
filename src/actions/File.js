import * as schema from './Schema'
import {getReferential, postReferential, delReferential, fileDownload} from '../utils/Action'

export const fetchFiles = () => (dispatch) => {
  var uri = '/api/files'
  return getReferential(schema.arrayOfFiles, uri)(dispatch)
}

export const addFile = (data) => (dispatch) => {
  var uri = '/api/files'
  return postReferential(schema.file, uri, data)(dispatch)
}

export const deleteFile = (fileId) => (dispatch) => {
  var uri = '/api/files/' + fileId
  return delReferential(uri, 'files', fileId)(dispatch)
}

export const downloadFile = (fileId, filename) => (dispatch) => {
  return fileDownload('/api/files/' + fileId, filename)(dispatch)
}
