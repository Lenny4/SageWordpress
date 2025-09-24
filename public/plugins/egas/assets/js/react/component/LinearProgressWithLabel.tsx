import * as React from "react";
import LinearProgress, {LinearProgressProps,} from "@mui/material/LinearProgress";
import Typography from "@mui/material/Typography";
import Box from "@mui/material/Box";

export function LinearProgressWithLabel(
  props: LinearProgressProps & { done: number; max: number },
) {
  let percent = 100;
  if (props.max > 0) {
    percent = (props.done / props.max) * 100;
  }
  return (
    <Box sx={{display: "flex", alignItems: "center"}}>
      <Box sx={{width: "100%", mr: 1}}>
        <LinearProgress variant="determinate" {...props} value={percent}/>
      </Box>
      <Box>
        <Typography
          variant="body2"
          sx={{color: "text.secondary", whiteSpace: "nowrap!important"}}
        >
          {`${Math.round(props.done)} / ${Math.round(props.max)}`}
          <Typography
            component="strong"
            sx={{marginLeft: 2, fontWeight: "bold"}}
          >{`${Math.round(percent)}%`}</Typography>
        </Typography>
      </Box>
    </Box>
  );
}
