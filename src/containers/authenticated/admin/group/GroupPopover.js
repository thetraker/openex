import React, {PropTypes, Component} from 'react';
import {connect} from 'react-redux';
import R from 'ramda'
import * as Constants from '../../../../constants/ComponentTypes'
import {Popover} from '../../../../components/Popover';
import {Menu} from '../../../../components/Menu'
import {Dialog} from '../../../../components/Dialog'
import {IconButton, FlatButton} from '../../../../components/Button'
import {Icon} from '../../../../components/Icon'
import {MenuItemLink, MenuItemButton} from "../../../../components/menu/MenuItem"
import {updateGroup, deleteGroup} from '../../../../actions/Group'
import GroupForm from './GroupForm'

const style = {
  position: 'absolute',
  top: '7px',
  right: 0,
}

class GroupPopover extends Component {
  constructor(props) {
    super(props);
    this.state = {
      openDelete: false,
      openEdit: false,
      openPopover: false
    }
  }

  handlePopoverOpen(event) {
    event.preventDefault()
    this.setState({
      openPopover: true,
      anchorEl: event.currentTarget,
    })
  }

  handlePopoverClose() {
    this.setState({openPopover: false})
  }

  handleOpenEdit() {
    this.setState({openEdit: true})
    this.handlePopoverClose()
  }

  handleCloseEdit() {
    this.setState({openEdit: false})
  }

  onSubmitEdit(data) {
    return this.props.updateGroup(this.props.group.group_id, data)
  }

  submitFormEdit() {
    this.refs.groupForm.submit()
  }

  handleOpenDelete() {
    this.setState({openDelete: true})
    this.handlePopoverClose()
  }

  handleCloseDelete() {
    this.setState({openDelete: false})
  }

  submitDelete() {
    this.props.deleteGroup(this.props.group.group_id)
    this.handleCloseDelete()
  }

  render() {
    const editActions = [
      <FlatButton label="Cancel" primary={true} onTouchTap={this.handleCloseEdit.bind(this)}/>,
      <FlatButton label="Update" primary={true} onTouchTap={this.submitFormEdit.bind(this)}/>,
    ]
    const deleteActions = [
      <FlatButton label="Cancel" primary={true} onTouchTap={this.handleCloseDelete.bind(this)}/>,
      <FlatButton label="Delete" primary={true} onTouchTap={this.submitDelete.bind(this)}/>,
    ]

    let initialValues = R.pick(['group_name'], this.props.group) //Pickup only needed fields

    return (
      <div style={style}>
        <IconButton onClick={this.handlePopoverOpen.bind(this)}>
          <Icon name={Constants.ICON_NAME_NAVIGATION_MORE_VERT}/>
        </IconButton>
        <Popover open={this.state.openPopover} anchorEl={this.state.anchorEl}
                 onRequestClose={this.handlePopoverClose.bind(this)}>
          <Menu multiple={false}>
            <MenuItemLink label="Edit" onTouchTap={this.handleOpenEdit.bind(this)}/>
            <MenuItemButton label="Delete" onTouchTap={this.handleOpenDelete.bind(this)}/>
          </Menu>
        </Popover>
        <Dialog title="Confirmation" modal={false} open={this.state.openDelete}
                onRequestClose={this.handleCloseDelete.bind(this)}
                actions={deleteActions}>
          Do you confirm the removing of this group?
        </Dialog>
        <Dialog title="Update the group" modal={false} open={this.state.openEdit}
                onRequestClose={this.handleCloseEdit.bind(this)}
                actions={editActions}>
          <GroupForm ref="groupForm" initialValues={initialValues}
                    onSubmit={this.onSubmitEdit.bind(this)}
                    onSubmitSuccess={this.handleCloseEdit.bind(this)}/>
        </Dialog>
      </div>
    )
  }
}

const select = (state) => {
  return {
    organizations: state.referential.entities.organizations
  }
}

GroupPopover.propTypes = {
  group: PropTypes.object,
  updateGroup: PropTypes.func,
  deleteGroup: PropTypes.func,
  organizations: PropTypes.object,
  children: PropTypes.node
}

export default connect(select, {updateGroup, deleteGroup})(GroupPopover)