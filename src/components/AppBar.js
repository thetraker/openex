import React, {PropTypes} from 'react';
import MUIAppBar from 'material-ui/AppBar';
import * as Constants from '../constants/ComponentTypes'

const appBarStyle = {
  [ Constants.APPBAR_TYPE_TOPBAR_NOICON ]: {
    marginBottom: 20
  }
}

const appBarTitleStyle = {
  [ Constants.APPBAR_TYPE_TOPBAR_NOICON ]: {
    textAlign: 'left',
    marginLeft: -10,
    cursor: 'pointer'
  },
  [ Constants.APPBAR_TYPE_TOPBAR ]: {
    marginLeft: 60,
    cursor: 'pointer'
  },
  [ Constants.APPBAR_TYPE_LEFTBAR ]: {
    cursor: 'pointer'
  },
}

export const AppBar = (props) => (
  <MUIAppBar
    title={props.title}
    onTitleTouchTap={props.onTitleTouchTap}
    onLeftIconButtonTouchTap={props.onLeftIconButtonTouchTap}
    iconElementRight={props.iconElementRight}
    showMenuIconButton={props.showMenuIconButton}
    titleStyle={appBarTitleStyle[props.type]}
    style={appBarStyle[props.type]}
  />
)

AppBar.propTypes = {
  title: PropTypes.string,
  type: PropTypes.string,
  onTitleTouchTap: PropTypes.func,
  onLeftIconButtonTouchTap: PropTypes.func,
  iconElementRight: PropTypes.element,
  showMenuIconButton: PropTypes.bool
}